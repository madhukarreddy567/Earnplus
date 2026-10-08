<?php

namespace Tests\Feature\Cron;

use App\Models\CronRun;
use App\Models\User;
use App\Models\Withdrawal;
use App\Models\WithdrawalMethod;
use App\Services\PayoutQueueService;
use App\Services\Withdrawals\PayoutDriver;
use Database\Seeders\SettingSeeder;
use Database\Seeders\WithdrawalMethodSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Phase 11: the payout queue processor — retries, backoff, stuck rows,
 * manual-driver rows, and never-half-applied guarantees.
 */
class PayoutQueueTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(SettingSeeder::class);
        $this->seed(WithdrawalMethodSeeder::class);
    }

    protected function makeProcessingWithdrawal(array $overrides = []): Withdrawal
    {
        $user = User::factory()->create(['email_verified_at' => now()]);
        $method = WithdrawalMethod::where('type', WithdrawalMethod::TYPE_UPI)->firstOrFail();

        $withdrawal = Withdrawal::create(array_merge([
            'user_id' => $user->id,
            'method_id' => $method->id,
            'coins_debited' => 2000,
            'amount_paise' => 2000,
            'tax_paise' => 0,
            'net_paise' => 2000,
            'currency' => 'INR',
            'details' => [],
            'status' => Withdrawal::STATUS_PROCESSING,
            'idempotency_key' => (string) Str::uuid(),
            'requested_at' => now(),
        ], $overrides));

        // created_at is not fillable — backdate directly when requested.
        if (isset($overrides['created_at'])) {
            Withdrawal::whereKey($withdrawal->id)->update(['created_at' => $overrides['created_at']]);
            $withdrawal->refresh();
        }

        return $withdrawal;
    }

    /**
     * @param 'completed'|'failed'|null $outcome null = the driver throws.
     */
    protected function serviceWithFakeDriver(?string $outcome): PayoutQueueService
    {
        $driver = new class($outcome) implements PayoutDriver {
            public function __construct(protected ?string $outcome)
            {
            }

            public function payout(Withdrawal $withdrawal): array
            {
                if ($this->outcome === null) {
                    throw new \RuntimeException('Driver exploded');
                }

                return [
                    'status' => $this->outcome,
                    'reference' => $this->outcome === 'completed' ? 'REF-123' : null,
                    'message' => "fake {$this->outcome}",
                ];
            }

            public function isLive(): bool
            {
                return true;
            }
        };

        return new PayoutQueueService(fn () => $driver);
    }

    /** @test */
    public function successful_retry_marks_withdrawal_completed(): void
    {
        $w = $this->makeProcessingWithdrawal();

        $result = $this->serviceWithFakeDriver('completed')->process();

        $this->assertSame(1, $result['completed']);
        $w = $w->fresh();
        $this->assertSame(Withdrawal::STATUS_COMPLETED, $w->status);
        $this->assertSame('REF-123', $w->payout_reference);
        $this->assertSame(1, $w->payout_attempts);
        $this->assertNull($w->next_retry_at);
    }

    /** @test */
    public function failed_retry_increments_attempts_and_schedules_backoff(): void
    {
        $w = $this->makeProcessingWithdrawal();

        $result = $this->serviceWithFakeDriver('failed')->process();

        $this->assertSame(1, $result['retried']);
        $w = $w->fresh();
        $this->assertSame(Withdrawal::STATUS_PROCESSING, $w->status);
        $this->assertSame(1, $w->payout_attempts);
        $this->assertNotNull($w->next_retry_at);
        $this->assertTrue($w->next_retry_at->gt(now()->addMinutes(50)));
    }

    /** @test */
    public function exhausted_retries_mark_the_row_failed_for_manual_review(): void
    {
        $w = $this->makeProcessingWithdrawal(['payout_attempts' => 4]); // max is 5

        $result = $this->serviceWithFakeDriver('failed')->process();

        $this->assertSame(1, $result['failed']);
        $w = $w->fresh();
        $this->assertSame(Withdrawal::STATUS_FAILED, $w->status);
        $this->assertSame(5, $w->payout_attempts);
        $this->assertNull($w->next_retry_at);
        $this->assertTrue($w->meta['needs_manual_review'] ?? false);
    }

    /** @test */
    public function manual_driver_rows_are_left_alone_for_humans(): void
    {
        $w = $this->makeProcessingWithdrawal();

        $manual = new class implements PayoutDriver {
            public function payout(Withdrawal $withdrawal): array
            {
                throw new \LogicException('should never be called');
            }

            public function isLive(): bool
            {
                return false;
            }
        };

        $result = (new PayoutQueueService(fn () => $manual))->process();

        $this->assertSame(0, $result['retried']);
        $this->assertSame(Withdrawal::STATUS_PROCESSING, $w->fresh()->status);
        $this->assertSame(0, $w->fresh()->payout_attempts);
    }

    /** @test */
    public function rows_not_yet_due_for_retry_are_skipped(): void
    {
        $w = $this->makeProcessingWithdrawal(['next_retry_at' => now()->addHour()]);

        $result = $this->serviceWithFakeDriver('completed')->process();

        $this->assertSame(0, $result['completed']);
        $this->assertSame(Withdrawal::STATUS_PROCESSING, $w->fresh()->status);
    }

    /** @test */
    public function a_throwing_driver_does_not_half_apply_state(): void
    {
        $w = $this->makeProcessingWithdrawal();

        $result = $this->serviceWithFakeDriver(null)->process();

        // The exception is caught per-row: the payout transaction rolled
        // back (status untouched, attempts NOT incremented since we can't
        // know what the driver did), the error is recorded in meta, a
        // backoff is scheduled, and a warning is raised.
        $w = $w->fresh();
        $this->assertSame(Withdrawal::STATUS_PROCESSING, $w->status);
        $this->assertSame(0, $w->payout_attempts);
        $this->assertNotNull($w->next_retry_at);
        $this->assertStringContainsString('Driver exception', $w->meta['last_payout_error']);
        $this->assertNotEmpty($result['warnings']);
    }

    /** @test */
    public function warnings_flag_stuck_manual_and_review_rows(): void
    {
        // Manual-driver row sitting in processing for 3 days: the queue
        // leaves it for humans but must warn about it.
        $this->makeProcessingWithdrawal(['created_at' => now()->subDays(3), 'requested_at' => now()->subDays(3)]);

        $manual = new class implements PayoutDriver {
            public function payout(Withdrawal $withdrawal): array
            {
                return ['status' => 'manual'];
            }

            public function isLive(): bool
            {
                return false;
            }
        };

        $result = (new PayoutQueueService(fn () => $manual))->process();

        $this->assertNotEmpty($result['warnings']);
        $this->assertStringContainsString('48h', $result['warnings'][0]);
    }

    /** @test */
    public function warnings_flag_rows_awaiting_manual_review(): void
    {
        $w = $this->makeProcessingWithdrawal(['payout_attempts' => 4]);
        $this->serviceWithFakeDriver('failed')->process();

        $result = $this->serviceWithFakeDriver('failed')->process();

        $this->assertStringContainsString('manual review', implode(' ', $result['warnings']));
    }

    /** @test */
    public function the_artisan_command_reports_warnings_end_to_end(): void
    {
        $this->makeProcessingWithdrawal(['created_at' => now()->subDays(3), 'requested_at' => now()->subDays(3)]);

        $this->artisan('cron:payout-queue')->assertSuccessful();

        $run = CronRun::latestFor('payout-queue');
        $this->assertSame(CronRun::STATUS_WARNING, $run->status);
        $this->assertStringContainsString('48h', $run->summary);
    }
}
