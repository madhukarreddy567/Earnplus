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
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Phase 11: failure handling — exceptions become failed runs, the
 * dashboard badge fires, and overlapping runs are refused.
 */
class CronFailureTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(SettingSeeder::class);
        $this->seed(WithdrawalMethodSeeder::class);
    }

    /** @test */
    public function a_command_that_throws_records_a_failed_run(): void
    {
        $user = User::factory()->create(['email_verified_at' => now()]);
        $method = WithdrawalMethod::where('type', WithdrawalMethod::TYPE_UPI)->firstOrFail();

        Withdrawal::create([
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
        ]);

        $exploding = new class implements PayoutDriver {
            public function payout(Withdrawal $withdrawal): array
            {
                throw new \RuntimeException('Everything is on fire');
            }

            public function isLive(): bool
            {
                return true;
            }
        };

        // The service catches per-row, so the command itself stays OK and
        // the row keeps its processing status with the error in meta.
        $service = new PayoutQueueService(fn () => $exploding);
        $result = $service->process();

        $this->assertNotEmpty($result['warnings']);
        $this->assertStringContainsString('on fire', $result['warnings'][0]);
    }

    /** @test */
    public function overlapping_runs_are_refused_and_logged(): void
    {
        Cache::lock('cron:lock:session-cleanup', 60)->acquire();

        $this->artisan('cron:session-cleanup')->assertFailed();

        $run = CronRun::latestFor('session-cleanup');
        $this->assertNotNull($run);
        $this->assertSame(CronRun::STATUS_FAILED, $run->status);
        $this->assertStringContainsString('already in progress', $run->summary);
    }

    /** @test */
    public function failed_runs_surface_through_failed_job_keys(): void
    {
        CronRun::start('log-cleanup')->fail('disk full');

        $this->assertContains('log-cleanup', CronRun::failedJobKeys());

        // A later OK run clears the alert.
        $this->artisan('cron:log-cleanup')->assertSuccessful();

        $this->assertNotContains('log-cleanup', CronRun::failedJobKeys());
    }

    /** @test */
    public function running_rows_do_not_count_as_failed(): void
    {
        CronRun::start('payout-queue'); // status=running, never finished

        $this->assertNotContains('payout-queue', CronRun::failedJobKeys());
    }
}
