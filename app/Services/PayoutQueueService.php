<?php

namespace App\Services;

use App\Models\Withdrawal;
use App\Models\WithdrawalMethod;
use App\Services\Withdrawals\PayoutDriver;
use Illuminate\Support\Facades\DB;

/**
 * Phase 11: the payout queue processor. Retries `processing` withdrawals
 * through their live payout drivers; rows that exhaust their retries are
 * marked failed with a manual-review flag for the admin queue.
 *
 * Only live API drivers are retried — manual-driver rows are already in
 * human hands and are left untouched (but flagged as warnings when they
 * sit too long).
 *
 * Every state change happens inside a row-locked transaction so a payout
 * can never be half-applied or double-retried.
 */
class PayoutQueueService
{
    /**
     * @param callable|null $driverFactory Receives a WithdrawalMethod, returns a PayoutDriver.
     *                                     Swappable in tests; defaults to the method's own driver().
     */
    public function __construct(
        protected $driverFactory = null,
    ) {
    }

    /**
     * Process every due retry. Returns a summary for the cron run log.
     *
     * @return array{retried: int, completed: int, failed: int, manual_waiting: int, warnings: list<string>}
     */
    public function process(): array
    {
        $maxAttempts = setting_int('payout_max_attempts', 5);
        $delayMinutes = max(1, setting_int('payout_retry_delay_minutes', 60));

        $due = Withdrawal::where('status', Withdrawal::STATUS_PROCESSING)
            ->where(function ($q) {
                $q->whereNull('next_retry_at')->orWhere('next_retry_at', '<=', now());
            })
            ->with('method')
            ->orderBy('id')
            ->get();

        $retried = 0;
        $completed = 0;
        $failed = 0;
        $warnings = [];

        foreach ($due as $withdrawal) {
            try {
                $outcome = DB::transaction(function () use ($withdrawal, $maxAttempts, $delayMinutes) {
                $w = Withdrawal::whereKey($withdrawal->id)->lockForUpdate()->first();

                if ($w === null || $w->status !== Withdrawal::STATUS_PROCESSING) {
                    return null; // Handled elsewhere while we waited.
                }

                $driver = $this->resolveDriver($w->method);

                if (! $driver->isLive()) {
                    return 'manual'; // Human completes these — not the queue's job.
                }

                $attempts = $w->payout_attempts + 1;
                $result = $driver->payout($w);
                $message = $result['message'] ?? 'Driver returned no message.';

                $meta = array_merge($w->meta ?? [], [
                    'queue_attempts' => $attempts,
                    'last_payout_error' => $message,
                    'last_queue_run_at' => now()->toIso8601String(),
                ]);

                if (($result['status'] ?? null) === 'completed') {
                    $w->update([
                        'status' => Withdrawal::STATUS_COMPLETED,
                        'payout_reference' => $result['reference'] ?? $w->payout_reference,
                        'payout_attempts' => $attempts,
                        'next_retry_at' => null,
                        'meta' => $meta,
                    ]);

                    return 'completed';
                }

                if ($attempts >= $maxAttempts) {
                    $w->update([
                        'status' => Withdrawal::STATUS_FAILED,
                        'payout_attempts' => $attempts,
                        'next_retry_at' => null,
                        'meta' => array_merge($meta, ['needs_manual_review' => true]),
                    ]);

                    return 'failed';
                }

                $w->update([
                    'payout_attempts' => $attempts,
                    'next_retry_at' => now()->addMinutes($delayMinutes),
                    'meta' => $meta,
                ]);

                return 'retried';
            });

            match ($outcome) {
                'completed' => $completed++,
                'failed' => $failed++,
                'retried' => $retried++,
                default => null,
            };
        } catch (\Throwable $e) {
            // Transport-level blowup (not a clean 'failed' result): the
            // payout transaction rolled back, so nothing half-applied.
            // Record the error diagnostically and back off — never
            // increment attempts, since we cannot know what the driver
            // did before throwing.
            report($e);

            DB::transaction(function () use ($withdrawal, $delayMinutes, $e) {
                $w = Withdrawal::whereKey($withdrawal->id)->lockForUpdate()->first();

                if ($w === null || $w->status !== Withdrawal::STATUS_PROCESSING) {
                    return;
                }

                $w->update([
                    'next_retry_at' => now()->addMinutes($delayMinutes),
                    'meta' => array_merge($w->meta ?? [], [
                        'last_payout_error' => 'Driver exception: ' . mb_substr($e->getMessage(), 0, 300),
                        'last_queue_run_at' => now()->toIso8601String(),
                    ]),
                ]);
            });

            $retried++;
            $warnings[] = "Driver exception on withdrawal #{$withdrawal->id}: {$e->getMessage()}";
        }
        } // foreach

        // Warnings: manual-driver rows sitting in processing too long,
        // and failed rows still awaiting review.
        $manualWaiting = Withdrawal::where('status', Withdrawal::STATUS_PROCESSING)
            ->where('created_at', '<', now()->subHours(48))
            ->count();

        $awaitingReview = Withdrawal::where('status', Withdrawal::STATUS_FAILED)
            ->whereJsonContains('meta->needs_manual_review', true)
            ->count();

        if ($manualWaiting > 0) {
            $warnings[] = "{$manualWaiting} manual payout(s) waiting over 48h for admin completion.";
        }
        if ($awaitingReview > 0) {
            $warnings[] = "{$awaitingReview} payout(s) exhausted retries and need manual review.";
        }

        return [
            'retried' => $retried,
            'completed' => $completed,
            'failed' => $failed,
            'manual_waiting' => $manualWaiting,
            'warnings' => $warnings,
        ];
    }

    protected function resolveDriver(WithdrawalMethod $method): PayoutDriver
    {
        if ($this->driverFactory !== null) {
            return call_user_func($this->driverFactory, $method);
        }

        return $method->driver();
    }
}
