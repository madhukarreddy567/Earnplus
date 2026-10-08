<?php

namespace Tests\Feature\Ads;

use App\Models\AdNetwork;
use App\Models\AdPlacement;
use App\Models\User;
use App\Services\AdService;
use Database\Seeders\AdSeeder;
use Database\Seeders\SettingSeeder;

/**
 * Shared builders for ad tests.
 */
trait CreatesAds
{
    protected AdService $ads;

    protected function setUpCreatesAds(): void
    {
        $this->seed(SettingSeeder::class);
        $this->seed(AdSeeder::class);
        $this->ads = app(AdService::class);
    }

    protected function makeNetwork(array $overrides = []): AdNetwork
    {
        return AdNetwork::create(array_merge([
            'name' => 'Test Network',
            'slug' => 'test-network-' . uniqid(),
            'enabled' => true,
            'type' => AdNetwork::TYPE_CUSTOM,
        ], $overrides));
    }

    protected function makePlacement(array $overrides = []): AdPlacement
    {
        $network = $overrides['network'] ?? $this->makeNetwork();
        unset($overrides['network']);

        return AdPlacement::create(array_merge([
            'network_id' => $network->id,
            'name' => 'Test Placement',
            'slug' => 'test-placement-' . uniqid(),
            'slot' => 'dashboard-banner',
            'enabled' => true,
            'placement_type' => AdPlacement::TYPE_BANNER,
            'device' => AdPlacement::DEVICE_ALL,
            'pages' => null,
            'frequency_cap_per_session' => 3,
            'priority' => 10,
            'coins' => 0,
            'custom_code' => '<div class="test-ad">TEST AD</div>',
        ], $overrides));
    }

    protected function makeRewardedPlacement(array $overrides = []): AdPlacement
    {
        return $this->makePlacement(array_merge([
            'slot' => 'rewarded',
            'placement_type' => AdPlacement::TYPE_REWARDED,
            'coins' => 5,
            'pages' => ['tasks', 'dashboard'],
        ], $overrides));
    }

    protected function makeUser(): User
    {
        return User::factory()->create(['email_verified_at' => now()]);
    }

    /**
     * A request with a working (array) session, for service-level tests.
     */
    protected function adRequest(string $userAgent = 'TestAgent/1.0'): \Illuminate\Http\Request
    {
        $request = \Illuminate\Http\Request::create('/', 'GET', [], [], [], [
            'HTTP_USER_AGENT' => $userAgent,
            'REMOTE_ADDR' => '127.0.0.1',
        ]);
        $request->setLaravelSession(app('session')->driver());

        return $request;
    }
}
