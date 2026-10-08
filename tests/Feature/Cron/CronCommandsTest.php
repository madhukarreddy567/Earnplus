<?php

namespace Tests\Feature\Cron;

use App\Models\CronRun;
use App\Models\DailyCheckin;
use App\Models\LoginLog;
use App\Models\Promotion;
use App\Models\User;
use Database\Seeders\SettingSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Phase 11: every cron command runs, records its run, and does its job.
 */
class CronCommandsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(SettingSeeder::class);
    }

    /** @test */
    public function all_five_commands_record_ok_runs(): void
    {
        foreach ([
            'cron:daily-maintenance',
            'cron:promotion-cleanup',
            'cron:payout-queue',
            'cron:log-cleanup',
            'cron:session-cleanup',
        ] as $signature) {
            $this->artisan($signature)->assertSuccessful();
        }

        $this->assertSame(5, CronRun::count());

        foreach (CronRun::all() as $run) {
            $this->assertSame(CronRun::STATUS_OK, $run->status);
            $this->assertNotEmpty($run->summary);
            $this->assertNotNull($run->finished_at);
            $this->assertNotNull($run->duration_ms);
        }
    }

    /** @test */
    public function promotion_cleanup_deactivates_expired_promotions(): void
    {
        $expired = Promotion::create([
            'name' => 'Old Promo',
            'slug' => 'old-promo-' . Str::random(4),
            'enabled' => true,
            'multiplier' => 2.00,
            'scope' => Promotion::SCOPE_GLOBAL,
            'starts_at' => now()->subDays(3),
            'ends_at' => now()->subDay(),
        ]);

        $live = Promotion::create([
            'name' => 'Live Promo',
            'slug' => 'live-promo-' . Str::random(4),
            'enabled' => true,
            'multiplier' => 2.00,
            'scope' => Promotion::SCOPE_GLOBAL,
            'starts_at' => now()->subHour(),
            'ends_at' => now()->addDays(3),
        ]);

        $this->artisan('cron:promotion-cleanup')->assertSuccessful();

        $this->assertFalse($expired->fresh()->enabled);
        $this->assertTrue($live->fresh()->enabled);

        $run = CronRun::latestFor('promotion-cleanup');
        $this->assertSame(CronRun::STATUS_OK, $run->status);
        $this->assertStringContainsString('1 expired', $run->summary);
    }

    /** @test */
    public function promotion_cleanup_warns_about_promotions_expiring_within_24h(): void
    {
        Promotion::create([
            'name' => 'Ending Soon',
            'slug' => 'ending-soon-' . Str::random(4),
            'enabled' => true,
            'multiplier' => 3.00,
            'scope' => Promotion::SCOPE_GLOBAL,
            'starts_at' => now()->subHour(),
            'ends_at' => now()->addHours(5),
        ]);

        $this->artisan('cron:promotion-cleanup')->assertSuccessful();

        $run = CronRun::latestFor('promotion-cleanup');
        $this->assertSame(CronRun::STATUS_WARNING, $run->status);
        $this->assertStringContainsString('Ending Soon', $run->summary);
    }

    /** @test */
    public function daily_maintenance_prunes_old_checkins_but_keeps_each_users_latest(): void
    {
        $user = User::factory()->create(['email_verified_at' => now()]);

        // All rows are beyond the 90-day default retention, but the
        // user's LATEST row must survive (streak math needs it).
        DailyCheckin::create(['user_id' => $user->id, 'checked_in_on' => now()->subDays(120)->toDateString(), 'streak' => 5]);
        DailyCheckin::create(['user_id' => $user->id, 'checked_in_on' => now()->subDays(100)->toDateString(), 'streak' => 6]);
        $latest = DailyCheckin::create(['user_id' => $user->id, 'checked_in_on' => now()->subDays(95)->toDateString(), 'streak' => 7]);

        $freshUser = User::factory()->create(['email_verified_at' => now()]);
        $recent = DailyCheckin::create(['user_id' => $freshUser->id, 'checked_in_on' => now()->subDay()->toDateString(), 'streak' => 1]);

        $this->artisan('cron:daily-maintenance')->assertSuccessful();

        $this->assertSame([$latest->id], DailyCheckin::where('user_id', $user->id)->pluck('id')->all());
        $this->assertSame([$recent->id], DailyCheckin::where('user_id', $freshUser->id)->pluck('id')->all());
    }

    /** @test */
    public function log_cleanup_prunes_old_logs_but_never_money_records(): void
    {
        DB::table('login_logs')->insert([
            'email' => 'old@example.com',
            'ip' => '1.2.3.4',
            'success' => true,
            'guard' => 'web',
            'created_at' => now()->subDays(120),
            'updated_at' => now()->subDays(120),
        ]);

        $this->artisan('cron:log-cleanup')->assertSuccessful();

        $this->assertSame(0, DB::table('login_logs')->count());

        $run = CronRun::latestFor('log-cleanup');
        $this->assertStringContainsString('login_logs: 1', $run->summary);
    }

    /** @test */
    public function session_cleanup_deletes_only_expired_sessions(): void
    {
        $lifetime = (int) config('session.lifetime', 120);

        DB::table('sessions')->insert([
            ['id' => Str::random(40), 'user_id' => null, 'ip_address' => '1.1.1.1', 'user_agent' => 't', 'payload' => 'x', 'last_activity' => now()->subMinutes($lifetime + 60)->timestamp],
            ['id' => Str::random(40), 'user_id' => null, 'ip_address' => '1.1.1.1', 'user_agent' => 't', 'payload' => 'x', 'last_activity' => now()->timestamp],
        ]);

        $this->artisan('cron:session-cleanup')->assertSuccessful();

        $this->assertSame(1, DB::table('sessions')->count());
    }
}
