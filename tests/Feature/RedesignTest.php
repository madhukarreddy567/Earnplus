<?php

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\Setting;
use App\Models\User;
use Database\Seeders\AdminSeeder;
use Database\Seeders\AdSeeder;
use Database\Seeders\OfferwallSeeder;
use Database\Seeders\SettingSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The mPaisa-style redesign: every page renders with the new mobile-money
 * chrome (top app bar, bottom nav, balance hero, quick actions).
 */
class RedesignTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(SettingSeeder::class);
        $this->seed(OfferwallSeeder::class);
        $this->seed(AdSeeder::class);
        Setting::set('email_auth_enabled', '1', 'features');
    }

    public function test_landing_uses_new_mobile_money_hero(): void
    {
        $response = $this->get('/');

        $response->assertOk();
        $response->assertSee('ep-landing-hero', false);
        $response->assertSee('ep-phone', false);
        $response->assertSee('Turn your free time into', false);
    }

    public function test_dashboard_has_balance_hero_quick_actions_and_bottom_nav(): void
    {
        $user = User::factory()->create(['email_verified_at' => now()]);

        $response = $this->actingAs($user)->get('/dashboard');

        $response->assertOk();
        $response->assertSee('ep-hero-card', false);
        $response->assertSee('ep-qa-grid', false);
        $response->assertSee('ep-bottomnav', false);
        $response->assertSee('ep-balance-pill', false);
        $response->assertSee('Recent activity', false);
        $response->assertSee('Refer &amp; earn', false);
    }

    public function test_tasks_page_uses_new_cards(): void
    {
        $user = User::factory()->create(['email_verified_at' => now()]);

        $response = $this->actingAs($user)->get('/tasks');

        $response->assertOk();
        $response->assertSee('ep-provider-card', false);
        $response->assertSee('Earn tasks', false);
    }

    public function test_spin_page_uses_new_stage(): void
    {
        $user = User::factory()->create(['email_verified_at' => now()]);

        $response = $this->actingAs($user)->get('/spin');

        $response->assertOk();
        $response->assertSee('ep-wheel-stage', false);
        $response->assertSee('id="wheel"', false);
    }

    public function test_login_page_uses_new_auth_design(): void
    {
        Setting::set('google_client_id', 'test-id', 'auth');
        Setting::set('google_client_secret', 'test-secret', 'auth');

        $response = $this->get('/login');

        $response->assertOk();
        $response->assertSee('ep-auth-card', false);
        $response->assertSee('Continue with Google', false);
    }

    public function test_admin_dashboard_uses_new_cards(): void
    {
        $this->seed(AdminSeeder::class);
        $admin = Admin::where('email', 'admin@earnplus.local')->firstOrFail();

        $response = $this->actingAs($admin, 'admin')->get('/admin');

        $response->assertOk();
        $response->assertSee('ep-card', false);
        $response->assertDontSee('ep-bottomnav', false);
    }

    public function test_topbar_shows_balance_pill_for_users_not_get_started(): void
    {
        $user = User::factory()->create(['email_verified_at' => now()]);

        $response = $this->actingAs($user)->get('/dashboard');

        $response->assertOk();
        // Logged-in users see their balance pill — never "Get Started".
        $response->assertSee('ep-balance-pill', false);
        $response->assertDontSee('Get Started', false);
    }

    public function test_guest_topbar_shows_get_started_when_auth_available(): void
    {
        $response = $this->get('/');

        $response->assertOk();
        $response->assertSee('Get Started', false);
    }
}
