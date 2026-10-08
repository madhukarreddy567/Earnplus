<?php

namespace App\Services\Withdrawals;

use App\Models\Admin;
use App\Models\CoinTransaction;
use App\Models\User;
use App\Models\Withdrawal;
use App\Models\WithdrawalMethod;
use App\Services\CoinService;
use App\Services\InsufficientBalanceException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Withdrawal business logic: money math, request flow, admin
 * approve/reject. All money is integer math — paise for INR, cents
 * for USD. Floats never touch money.
 */
class WithdrawalService
{
    public function __construct(protected CoinService $coins)
    {
    }

    // ------------------------------------------------------------------
    // Money math (integers only)
    // ------------------------------------------------------------------

    /**
     * Coins → paise. 100 coins = ₹1 (100 paise) by default.
     */
    public function coinsToPaise(int $coins): int
    {
        $perRupee = setting_int('coins_per_rupee', 100);
        if ($perRupee <= 0) {
            $perRupee = 100;
        }

        return intdiv($coins * 100, $perRupee);
    }

    /**
     * Paise → coins needed (rounded UP so the user always covers it).
     */
    public function paiseToCoins(int $paise): int
    {
        $perRupee = setting_int('coins_per_rupee', 100);
        if ($perRupee <= 0) {
            $perRupee = 100;
        }

        return (int) ceil($paise * $perRupee / 100);
    }

    /**
     * Tax on a paise amount. Off by default (setting).
     */
    public function taxPaise(int $amountPaise): int
    {
        if (! setting_bool('withdrawal_tax_enabled', false)) {
            return 0;
        }

        $percent = setting_int('withdrawal_tax_percent', 0);
        if ($percent <= 0) {
            return 0;
        }

        return intdiv($amountPaise * $percent, 100);
    }

    /**
     * Net paise → USD cents via the usd_per_rupee setting.
     */
    public function paiseToUsdCents(int $netPaise): int
    {
        $rate = (float) (setting('usd_per_rupee', '0.012') ?? '0.012');
        if ($rate <= 0) {
            $rate = 0.012;
        }

        return (int) round($netPaise * $rate);
    }

    /**
     * Full quote for a requested amount (smallest unit of the method's
     * currency): gross paise, coins needed, tax, net payout.
     *
     * @return array{coins:int, amount_paise:int, tax_paise:int, net_paise:int, net_usd_cents:?int}
     */
    public function quote(WithdrawalMethod $method, int $amountSmallestUnit): array
    {
        if ($method->isUsd()) {
            // cents → paise via USD rate, then coins from paise.
            $rate = (float) (setting('usd_per_rupee', '0.012') ?? '0.012');
            if ($rate <= 0) {
                $rate = 0.012;
            }
            $amountPaise = (int) round($amountSmallestUnit / $rate);
        } else {
            $amountPaise = $amountSmallestUnit;
        }

        $taxPaise = $this->taxPaise($amountPaise);
        $netPaise = $amountPaise - $taxPaise;

        return [
            'coins' => $this->paiseToCoins($amountPaise),
            'amount_paise' => $amountPaise,
            'tax_paise' => $taxPaise,
            'net_paise' => $netPaise,
            'net_usd_cents' => $method->isUsd() ? $this->paiseToUsdCents($netPaise) : null,
        ];
    }

    /**
     * How many withdrawal requests this user made today.
     */
    public function requestsToday(User $user): int
    {
        return Withdrawal::where('user_id', $user->id)
            ->whereDate('requested_at', today())
            ->count();
    }

    // ------------------------------------------------------------------
    // User request flow
    // ------------------------------------------------------------------

    /**
     * Create a withdrawal request. Idempotent on $idempotencyKey:
     * a repeated submit returns the original withdrawal.
     *
     * @throws RuntimeException on business-rule violations
     */
    public function request(
        User $user,
        WithdrawalMethod $method,
        int $amountSmallestUnit,
        array $details,
        string $idempotencyKey
    ): Withdrawal {
        if (! setting_bool('withdrawals_enabled', true)) {
            throw new RuntimeException('Withdrawals are currently disabled.');
        }

        $method = WithdrawalMethod::whereKey($method->id)->firstOrFail();
        if (! $method->enabled) {
            throw new RuntimeException('This payout method is currently disabled.');
        }

        if ($amountSmallestUnit < $method->min_amount || $amountSmallestUnit > $method->max_amount) {
            throw new RuntimeException('Amount is outside this method\'s allowed range.');
        }

        $maxPerDay = setting_int('withdrawal_max_per_day', 3);
        if ($this->requestsToday($user) >= $maxPerDay) {
            throw new RuntimeException('Daily withdrawal limit reached. Try again tomorrow.');
        }

        $quote = $this->quote($method, $amountSmallestUnit);

        return DB::transaction(function () use ($user, $method, $quote, $details, $idempotencyKey) {
            $existing = Withdrawal::where('idempotency_key', $idempotencyKey)->first();
            if ($existing !== null) {
                return $existing;
            }

            $tx = $this->coins->debit(
                $user,
                $quote['coins'],
                CoinTransaction::SOURCE_WITHDRAWAL,
                "withdrawal:{$user->id}:{$idempotencyKey}",
                "withdraw:{$idempotencyKey}",
                ['method' => $method->type, 'amount_paise' => $quote['amount_paise']]
            );

            try {
                return Withdrawal::create([
                    'user_id' => $user->id,
                    'method_id' => $method->id,
                    'coins_debited' => $quote['coins'],
                    'amount_paise' => $quote['amount_paise'],
                    'tax_paise' => $quote['tax_paise'],
                    'net_paise' => $quote['net_paise'],
                    'net_usd_cents' => $quote['net_usd_cents'],
                    'currency' => $method->currency,
                    'details' => $details,
                    'status' => Withdrawal::STATUS_PENDING,
                    'idempotency_key' => $idempotencyKey,
                    'meta' => ['coin_transaction_id' => $tx->id],
                ]);
            } catch (\Illuminate\Database\QueryException $e) {
                // Lost a race: another request created the row first.
                if ((int) ($e->errorInfo[1] ?? 0) === 1062
                    || str_contains($e->getMessage(), 'Duplicate entry')) {
                    return Withdrawal::where('idempotency_key', $idempotencyKey)->firstOrFail();
                }

                throw $e;
            }
        });
    }

    // ------------------------------------------------------------------
    // Admin actions
    // ------------------------------------------------------------------

    /**
     * Approve a pending withdrawal: attempt the payout driver, then
     * move to processing/completed/failed. Idempotent — non-pending
     * rows return their current status untouched.
     */
    public function approve(Withdrawal $withdrawal, Admin $admin, ?string $note = null): array
    {
        return DB::transaction(function () use ($withdrawal, $admin, $note) {
            $withdrawal = Withdrawal::whereKey($withdrawal->id)->lockForUpdate()->firstOrFail();

            if (! $withdrawal->isPending()) {
                return ['status' => $withdrawal->status, 'withdrawal_id' => $withdrawal->id];
            }

            $method = $withdrawal->method;
            $driver = $method->driver();
            $result = $driver->payout($withdrawal);

            $status = match ($result['status']) {
                'completed' => Withdrawal::STATUS_COMPLETED,
                'failed' => Withdrawal::STATUS_FAILED,
                default => Withdrawal::STATUS_PROCESSING, // processing | manual
            };

            $meta = array_merge($withdrawal->meta ?? [], array_filter([
                'driver' => get_class($driver),
                'driver_live' => $driver->isLive(),
                'driver_message' => $result['message'] ?? null,
                'processed_by_admin_id' => $admin->id,
                'processed_by_admin_email' => $admin->email,
            ]));

            $withdrawal->update([
                'status' => $status,
                'payout_reference' => $result['reference'],
                'admin_note' => $note,
                'processed_at' => now(),
                'meta' => $meta,
            ]);

            return [
                'status' => $status,
                'withdrawal_id' => $withdrawal->id,
                'driver_live' => $driver->isLive(),
                'driver_message' => $result['message'] ?? null,
            ];
        });
    }

    /**
     * Reject a pending withdrawal and refund the exact coins debited.
     * Idempotent — a second reject returns the current status and
     * never double-refunds.
     */
    public function reject(Withdrawal $withdrawal, Admin $admin, ?string $reason = null): array
    {
        return DB::transaction(function () use ($withdrawal, $admin, $reason) {
            $withdrawal = Withdrawal::whereKey($withdrawal->id)->lockForUpdate()->firstOrFail();

            if (! $withdrawal->isPending()) {
                return ['status' => $withdrawal->status, 'withdrawal_id' => $withdrawal->id];
            }

            $this->coins->credit(
                $withdrawal->user,
                $withdrawal->coins_debited,
                CoinTransaction::SOURCE_WITHDRAWAL_REFUND,
                "withdrawal_refund:{$withdrawal->id}",
                "withdrawal:{$withdrawal->id}",
                ['reason' => $reason, 'processed_by_admin_id' => $admin->id]
            );

            $withdrawal->update([
                'status' => Withdrawal::STATUS_REJECTED,
                'admin_note' => $reason,
                'processed_at' => now(),
                'meta' => array_merge($withdrawal->meta ?? [], [
                    'rejected_by_admin_id' => $admin->id,
                    'rejected_by_admin_email' => $admin->email,
                ]),
            ]);

            return ['status' => Withdrawal::STATUS_REJECTED, 'withdrawal_id' => $withdrawal->id];
        });
    }

    /**
     * A fresh idempotency key for the withdraw form.
     */
    public static function newIdempotencyKey(): string
    {
        return Str::uuid()->toString();
    }
}
