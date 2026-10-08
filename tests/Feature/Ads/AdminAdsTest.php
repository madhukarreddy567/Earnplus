<?php

namespace Tests\Feature\Ads;

use App\Models\AdNetwork;
use App\Models\AdPlacement;
use App\Models\AdReward;
use Database\Seeders\AdminSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminAdsTest extends TestCase
{
    use RefreshDatabase;
    use CreatesAds;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpCreatesAds();
        $this->loginAsAdmin();
    }

    protected function loginAsAdmin(): void
    {
        $this->seed(AdminSeeder::class);

        $this->post('/admin/login', [
            'email' => 'admin@earnplus.local',
            'password' => 'ChangeMe123!',
        ]);

        $this->assertAuthenticatedAs(
            \App\Models\Admin::where('email', 'admin@earnplus.local')->first(),
            'admin'
        );
    }

    public function test_guests_are_redirected_to_admin_login(): void
    {
        auth('admin')->logout();

        $this->get(route('admin.ads.index'))->assertRedirect(route('admin.login'));
    }

    public function test_users_cannot_open_ads_admin(): void
    {
        auth('admin')->logout();
        $this->actingAs($this->makeUser());

        // A web-guard session is not an admin session: the guard-aware
        // auth layer sends them to the admin login (same as guests).
        $this->get(route('admin.ads.index'))->assertRedirect(route('admin.login'));
    }

    public function test_admin_sees_networks_placements_and_stats(): void
    {
        $response = $this->get(route('admin.ads.index'));

        $response->assertOk();
        $response->assertSee('Google AdSense');
        $response->assertSee('Adsterra');
        $response->assertSee('Monetag');
        $response->assertSee('PropellerAds');
        $response->assertSee('Demo rewarded ad');
    }

    public function test_admin_can_create_a_network(): void
    {
        $response = $this->post(route('admin.ads.networks.store'), [
            'name' => 'TestNet',
            'slug' => 'testnet',
            'type' => AdNetwork::TYPE_ADSTERRA,
            'enabled' => '1',
        ]);

        $response->assertRedirect(route('admin.ads.index'));

        $network = AdNetwork::where('slug', 'testnet')->firstOrFail();
        $this->assertTrue($network->enabled);
        $this->assertSame(AdNetwork::TYPE_ADSTERRA, $network->type);
    }

    public function test_network_validation_rejects_bad_slug(): void
    {
        $this->post(route('admin.ads.networks.store'), [
            'name' => 'Bad',
            'slug' => 'Bad Slug!',
            'type' => AdNetwork::TYPE_CUSTOM,
        ])->assertSessionHasErrors('slug');
    }

    public function test_admin_can_update_a_network(): void
    {
        $network = AdNetwork::where('slug', 'adsense')->firstOrFail();

        $this->put(route('admin.ads.networks.update', $network), [
            'name' => 'Google AdSense',
            'slug' => 'adsense',
            'type' => AdNetwork::TYPE_ADSENSE,
            'enabled' => '1',
        ])->assertRedirect(route('admin.ads.index'));

        $this->assertTrue($network->fresh()->enabled);
    }

    public function test_network_with_placements_cannot_be_deleted(): void
    {
        $network = AdNetwork::where('slug', 'demo')->firstOrFail();

        $this->delete(route('admin.ads.networks.destroy', $network))
            ->assertRedirect(route('admin.ads.index'));

        $this->assertNotNull($network->fresh());
    }

    public function test_admin_can_create_a_placement(): void
    {
        $network = AdNetwork::where('slug', 'adsense')->firstOrFail();

        $response = $this->post(route('admin.ads.placements.store'), [
            'name' => 'AdSense dashboard banner',
            'slug' => 'adsense-dashboard-banner',
            'slot' => 'dashboard-banner',
            'network_id' => $network->id,
            'placement_type' => AdPlacement::TYPE_BANNER,
            'device' => AdPlacement::DEVICE_ALL,
            'pages' => 'dashboard',
            'frequency_cap_per_session' => 2,
            'priority' => 20,
            'coins' => 0,
            'custom_code' => '<ins class="adsbygoogle"></ins>',
            'enabled' => '1',
        ]);

        $response->assertRedirect(route('admin.ads.index'));

        $placement = AdPlacement::where('slug', 'adsense-dashboard-banner')->firstOrFail();
        $this->assertSame(['dashboard'], $placement->pages);
        $this->assertSame(20, $placement->priority);
        $this->assertTrue($placement->enabled);
    }

    public function test_admin_can_toggle_and_reprioritize_a_placement(): void
    {
        $placement = AdPlacement::where('slug', 'demo-rewarded')->firstOrFail();

        $this->put(route('admin.ads.placements.update', $placement), [
            'name' => $placement->name,
            'slug' => $placement->slug,
            'slot' => $placement->slot,
            'network_id' => $placement->network_id,
            'placement_type' => $placement->placement_type,
            'device' => $placement->device,
            'pages' => 'tasks, dashboard',
            'frequency_cap_per_session' => 5,
            'priority' => 99,
            'coins' => 5,
            'custom_code' => $placement->custom_code,
            // 'enabled' omitted → checkbox off
        ])->assertRedirect(route('admin.ads.index'));

        $fresh = $placement->fresh();
        $this->assertFalse($fresh->enabled);
        $this->assertSame(99, $fresh->priority);
    }

    public function test_admin_can_delete_a_placement(): void
    {
        $placement = $this->makePlacement();

        $this->delete(route('admin.ads.placements.destroy', $placement))
            ->assertRedirect(route('admin.ads.index'));

        $this->assertNull($placement->fresh());
    }

    public function test_reward_log_shows_payouts(): void
    {
        $user = $this->makeUser();
        $placement = AdPlacement::where('slug', 'demo-rewarded')->firstOrFail();

        AdReward::create([
            'user_id' => $user->id,
            'placement_id' => $placement->id,
            'coins' => 5,
            'idempotency_key' => 'log-test-1',
        ]);

        $response = $this->get(route('admin.ads.rewards'));

        $response->assertOk();
        $response->assertSee($user->name);
        $response->assertSee('log-test-1');
    }
}
