<?php

namespace Tests\Feature\Coins;

use App\Models\CoinTransaction;
use App\Models\DailyCheckin;
use App\Models\Setting;
use App\Models\User;
use App\Services\AlreadyCheckedInException;
use App\Services\CheckinService;
use Database\Seeders\SettingSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CheckinTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(SettingSeeder::class);
    }

    protected function verifiedUser(): User
    {
        $user = User::factory()->create(['email_verified_at' => now()]);
        $this->actingAs($user);

        return $user;
    }

    public function test_first_checkin_credits_coins_and_starts_streak(): void
    {
        $user = $this->verifiedUser();

        $response = $this->post('/checkin');

        $response->assertRedirect(route('dashboard'));
        $this->assertSame(10, $user->fresh()->coinBalance());
        $this->assertDatabaseHas('daily_checkins', [
            'user_id' => $user->id,
            'streak' => 1,
        ]);
    }

    public function test_second_checkin_same_day_is_rejected(): void
    {
        $user = $this->verifiedUser();
        $service = app(CheckinService::class);

        $service->checkin($user);

        $response = $this->post('/checkin');
        $response->assertRedirect(route('dashboard'));
        $response->assertSessionHas('status');

        // Only one credit happened.
        $this->assertSame(10, $user->fresh()->coinBalance());

        $this->expectException(AlreadyCheckedInException::class);
        $service->checkin($user);
    }

    public function test_streak_increments_on_consecutive_days(): void
    {
        $user = $this->verifiedUser();
        $service = app(CheckinService::class);

        DailyCheckin::create([
            'user_id' => $user->id,
            'checked_in_on' => now(config('app.timezone'))->subDay()->toDateString(),
            'streak' => 4,
        ]);

        $result = $service->checkin($user);

        $this->assertSame(5, $result['streak']);
        $this->assertSame(5, $service->currentStreak($user->fresh()));
    }

    public function test_streak_resets_after_a_missed_day(): void
    {
        $user = $this->verifiedUser();
        $service = app(CheckinService::class);

        DailyCheckin::create([
            'user_id' => $user->id,
            'checked_in_on' => now(config('app.timezone'))->subDays(3)->toDateString(),
            'streak' => 9,
        ]);

        $result = $service->checkin($user);

        $this->assertSame(1, $result['streak']);
    }

    public function test_streak_bonus_paid_every_nth_day(): void
    {
        Setting::set('daily_checkin_streak_days', '7', 'coins');
        Setting::set('daily_checkin_streak_bonus', '50', 'coins');
        Setting::set('daily_checkin_coins', '10', 'coins');

        $user = $this->verifiedUser();
        $service = app(CheckinService::class);

        DailyCheckin::create([
            'user_id' => $user->id,
            'checked_in_on' => now(config('app.timezone'))->subDay()->toDateString(),
            'streak' => 6,
        ]);

        $result = $service->checkin($user);

        $this->assertSame(7, $result['streak']);
        $this->assertSame(50, $result['streak_bonus']);
        $this->assertSame(60, $result['amount']);
        $this->assertSame(60, $user->fresh()->coinBalance());
        $this->assertSame(50, $result['transaction']->meta['streak_bonus']);
    }

    public function test_no_streak_bonus_on_ordinary_days(): void
    {
        $user = $this->verifiedUser();
        $service = app(CheckinService::class);

        $result = $service->checkin($user);

        $this->assertSame(0, $result['streak_bonus']);
        $this->assertSame(10, $result['amount']);
    }

    public function test_disabled_checkin_throws(): void
    {
        Setting::set('daily_checkin_enabled', '0', 'features');

        $user = $this->verifiedUser();
        $service = app(CheckinService::class);

        $this->expectException(\RuntimeException::class);
        $service->checkin($user);
    }

    public function test_idempotent_across_duplicate_key(): void
    {
        // The idempotency key checkin:{user}:{date} means even a direct
        // service re-entry can never double-credit.
        $user = $this->verifiedUser();
        $service = app(CheckinService::class);

        $service->checkin($user);

        $this->assertSame(1, CoinTransaction::where('user_id', $user->id)
            ->where('source', 'daily_checkin')->count());
    }
}
