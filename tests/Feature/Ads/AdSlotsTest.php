<?php

namespace Tests\Feature\Ads;

use App\Models\Admin;
use App\Models\AdPlacement;
use App\Models\CoinTransaction;
use App\Services\CoinService;
use App\Models\Setting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Phase 5 completion gaps: auth-aware navbar, sidebar slot, interstitial slot.
 */
class AdSlotsTest extends TestCase
{
    use RefreshDatabase;
    use CreatesAds;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpCreatesAds();
    }

    public function test_guest_navbar_shows_get_started(): void
    {
        Setting::set('email_auth_enabled', '1', 'features');

        $response = $this->get('/');

        $response->assertOk();
        $response->assertSee('Get Started');
        $response->assertDontSee('data-bs-toggle="dropdown"', false);
    }

    public function test_guest_navbar_hides_get_started_when_no_auth_available(): void
    {
        // E-mail auth off (default) and Google unconfigured: no dead button.
        $response = $this->get('/');

        $response->assertOk();
        $response->assertDontSee('Get Started');
    }

    public function test_logged_in_user_navbar_shows_balance_and_account_menu(): void
    {
        $user = $this->makeUser();
        app(CoinService::class)->credit(
            $user,
            250,
            CoinTransaction::SOURCE_ADMIN_ADJUST,
            'test-navbar-' . $user->id,
            'test'
        );

        // Tasks page has no balance hero — the only count-up is the navbar badge.
        $response = $this->actingAs($user)->get('/tasks');

        $response->assertOk();
        $response->assertDontSee('Get Started');
        $response->assertSee($user->name);
        $response->assertSee('data-countup="250"', false);
        $response->assertSee('data-bs-toggle="dropdown"', false);
        $response->assertSee(route('spin'));
    }

    public function test_admin_navbar_shows_admin_menu_not_get_started(): void
    {
        $admin = Admin::create([
            'name' => 'Boss',
            'email' => 'boss@example.com',
            'password' => 'secret123',
            'role' => 'super_admin',
            'is_active' => true,
        ]);

        $response = $this->actingAs($admin, 'admin')->get('/admin');

        $response->assertOk();
        $response->assertSee('Admin panel');
        $response->assertDontSee('Get Started');
    }

    public function test_sidebar_slot_renders_when_eligible(): void
    {
        $this->makePlacement([
            'slot' => 'sidebar',
            'placement_type' => AdPlacement::TYPE_SIDEBAR,
            'pages' => null,
        ]);

        $response = $this->actingAs($this->makeUser())->get('/dashboard');

        $response->assertOk();
        $response->assertSee('data-ad-slot="sidebar"', false);
        $response->assertSee('data-sidebar-ad', false);
    }

    public function test_sidebar_slot_renders_nothing_when_ineligible(): void
    {
        $response = $this->actingAs($this->makeUser())->get('/dashboard');

        $response->assertOk();
        $response->assertDontSee('data-ad-slot="sidebar"');
    }

    public function test_interstitial_renders_when_eligible(): void
    {
        $this->makePlacement([
            'slot' => 'interstitial',
            'placement_type' => AdPlacement::TYPE_INTERSTITIAL,
            'pages' => null,
            'frequency_cap_per_session' => 5,
        ]);

        $response = $this->get('/');

        $response->assertOk();
        $response->assertSee('id="epInterstitial"', false);
        $response->assertSee('data-ad-slot="interstitial"', false);
    }

    public function test_interstitial_container_hidden_without_placement(): void
    {
        $response = $this->get('/');

        $response->assertOk();
        // The container exists (hidden) but no ad code rendered inside.
        $response->assertSee('id="epInterstitial"', false);
        $response->assertDontSee('data-ad-slot="interstitial"');
    }

    public function test_interstitial_respects_session_cap(): void
    {
        $this->makePlacement([
            'slot' => 'interstitial',
            'placement_type' => AdPlacement::TYPE_INTERSTITIAL,
            'pages' => null,
            'frequency_cap_per_session' => 1,
        ]);

        $this->get('/')->assertSee('data-ad-slot="interstitial"', false);
        // Second page view in the same session: cap reached, no ad.
        $this->get('/')->assertDontSee('data-ad-slot="interstitial"');
    }

    public function test_interstitial_and_sidebar_never_render_for_admins(): void
    {
        $this->makePlacement([
            'slot' => 'interstitial',
            'placement_type' => AdPlacement::TYPE_INTERSTITIAL,
            'pages' => null,
        ]);
        $this->makePlacement([
            'slot' => 'sidebar',
            'placement_type' => AdPlacement::TYPE_SIDEBAR,
            'pages' => null,
        ]);

        $admin = Admin::create([
            'name' => 'Boss',
            'email' => 'boss2@example.com',
            'password' => 'secret123',
            'role' => 'super_admin',
            'is_active' => true,
        ]);

        $response = $this->actingAs($admin, 'admin')->get('/admin');

        $response->assertOk();
        $response->assertDontSee('epInterstitial');
        $response->assertDontSee('data-sidebar-ad');
        $response->assertDontSee('data-ad-slot="interstitial"');
        $response->assertDontSee('data-ad-slot="sidebar"');
    }
}
