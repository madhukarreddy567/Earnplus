<?php

namespace Tests\Feature\AdminPanel;

use App\Models\AdNetwork;
use App\Models\AdPlacement;
use App\Models\Promotion;
use App\Models\Setting;
use App\Models\User;
use App\Services\PromotionService;
use Database\Seeders\SettingSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Phase 9: module toggles kill their features instantly —
 * offerwalls, ads and promotions.
 */
class ModuleToggleTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(SettingSeeder::class);
    }

    protected function loginAsUser(): User
    {
        $user = User::factory()->create(['email_verified_at' => now()]);

        $this->actingAs($user);

        return $user;
    }

    /** @test */
    public function offerwalls_toggle_gates_task_routes(): void
    {
        $this->loginAsUser();

        Setting::set('offerwalls_enabled', '1', 'features');
        $this->get('/tasks')->assertOk();

        Setting::set('offerwalls_enabled', '0', 'features');
        $this->get('/tasks')->assertNotFound();
        $this->get('/tasks/out/demo')->assertNotFound();
    }

    /** @test */
    public function ads_toggle_gates_rewarded_claims(): void
    {
        $this->loginAsUser();

        $network = AdNetwork::create([
            'name' => 'N', 'slug' => 'n-' . uniqid(), 'enabled' => true,
            'type' => AdNetwork::TYPE_CUSTOM, 'config' => [],
        ]);
        $placement = AdPlacement::create([
            'network_id' => $network->id, 'name' => 'R', 'slug' => 'r-' . uniqid(),
            'slot' => 'rewarded', 'enabled' => true,
            'placement_type' => AdPlacement::TYPE_REWARDED,
            'device' => AdPlacement::DEVICE_ALL, 'pages' => null,
            'frequency_cap_per_session' => 5, 'priority' => 10, 'coins' => 5,
            'custom_code' => '<div>x</div>',
        ]);

        Setting::set('ads_enabled', '1', 'features');
        // Enabled: the request reaches the controller (it fails claim
        // validation instead of 404ing at the gate).
        $enabled = $this->post('/ads/reward/' . $placement->slug)->status();
        $this->assertNotSame(404, $enabled);

        Setting::set('ads_enabled', '0', 'features');
        $this->post('/ads/reward/' . $placement->slug)->assertNotFound();
    }

    /** @test */
    public function promotions_toggle_kills_all_multipliers(): void
    {
        Promotion::create([
            'name' => 'Test 2X', 'slug' => 'test-2x-' . uniqid(), 'enabled' => true,
            'multiplier' => 2.00, 'scope' => Promotion::SCOPE_GLOBAL,
            'starts_at' => now()->subDay(), 'ends_at' => now()->addDay(),
            'priority' => 10,
        ]);

        $service = app(PromotionService::class);

        Setting::set('promotions_enabled', '1', 'features');
        $this->assertTrue($service->activePromotions('spin')->isNotEmpty());

        Setting::set('promotions_enabled', '0', 'features');
        $this->assertTrue($service->activePromotions('spin')->isEmpty());
        $this->assertSame(100, $service->applyMultipliers(100, 'spin')['coins']);
    }
}
