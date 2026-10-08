<?php

namespace Tests\Feature\Coins;

use App\Models\CoinTransaction;
use App\Models\Setting;
use App\Models\User;
use App\Services\CheckinService;
use App\Services\CoinService;
use App\Services\SpinService;
use Database\Seeders\SettingSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DashboardTest extends TestCase
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

    public function test_dashboard_renders_wallet_overview(): void
    {
        $user = $this->verifiedUser();
        app(CoinService::class)->credit($user, 250, CoinTransaction::SOURCE_SIGNUP_BONUS, 'k1');

        $response = $this->get('/dashboard');

        $response->assertOk();
        $response->assertSee('Total balance');
        $response->assertSee('data-countup="250"', false);
        $response->assertSee('₹2.50'); // 250 coins / 100 per rupee
        $response->assertSee($user->fresh()->referral_code);
        $response->assertSee('Daily check-in');
        $response->assertSee('>Spin<', false);
        $response->assertSee('Recent activity');
        $response->assertSee('Signup bonus');
    }

    public function test_dashboard_shows_todays_earnings(): void
    {
        $user = $this->verifiedUser();
        $coins = app(CoinService::class);
        $coins->credit($user, 100, CoinTransaction::SOURCE_SIGNUP_BONUS, 'k1');

        $response = $this->get('/dashboard');

        $response->assertSee('+100');
        $response->assertSee('earned today');
    }

    public function test_dashboard_reflects_checkin_state(): void
    {
        $user = $this->verifiedUser();
        app(CheckinService::class)->checkin($user);

        $response = $this->get('/dashboard');

        $response->assertOk();
        $response->assertSee('Done for today');
        $response->assertSee('1-day');
    }

    public function test_dashboard_reflects_spin_state(): void
    {
        $user = $this->verifiedUser();
        Setting::set('spin_win_probability', '0', 'spin');

        app(SpinService::class)->spin($user, '127.0.0.1', null);

        // The spin page (linked from the dashboard quick actions) shows it.
        $response = $this->get('/spin');

        $response->assertOk();
        $response->assertSee('No spins left');
    }

    public function test_dashboard_shows_referral_invites(): void
    {
        $user = $this->verifiedUser();
        User::factory()->create(['referred_by' => $user->id, 'name' => 'Invited Friend']);

        $response = $this->get('/dashboard');

        $response->assertOk();
        $response->assertSee('Invited Friend');
    }

    public function test_guest_cannot_open_dashboard(): void
    {
        $this->get('/dashboard')->assertRedirect('/login');
    }

    public function test_unverified_user_cannot_open_dashboard(): void
    {
        $user = User::factory()->unverified()->create();
        $this->actingAs($user);

        $this->get('/dashboard')->assertRedirect('/email/verify');
    }

    public function test_banner_reads_from_settings(): void
    {
        $this->verifiedUser();
        Setting::set('banner_title', 'Mega Festival Bonanza', 'branding');

        $this->get('/dashboard')->assertSee('Mega Festival Bonanza');
    }

    public function test_banner_hidden_when_disabled(): void
    {
        $this->verifiedUser();
        Setting::set('banner_enabled', '0', 'branding');
        Setting::set('banner_title', 'Should Not Appear', 'branding');

        $this->get('/dashboard')->assertDontSee('Should Not Appear');
    }
}
