<?php

namespace Tests\Feature\Ads;

use App\Models\AdReward;
use App\Models\CoinTransaction;
use App\Models\Setting;
use App\Models\Wallet;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RewardedAdTest extends TestCase
{
    use RefreshDatabase;
    use CreatesAds;

    protected string $demoSlug = 'demo-rewarded';

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpCreatesAds();
    }

    /**
     * POST a rewarded claim with controlled IP + user agent.
     */
    protected function claimAs($user, string $slug, string $ip = '127.0.0.1', string $ua = 'TestAgent/1.0')
    {
        $this->actingAs($user);

        return $this->call(
            'POST',
            route('ads.reward', $slug),
            [],
            [],
            [],
            ['REMOTE_ADDR' => $ip, 'HTTP_USER_AGENT' => $ua, 'HTTP_ACCEPT' => 'application/json']
        );
    }

    protected function relaxLimits(): void
    {
        Setting::set('rewarded_ad_min_interval_seconds', '0', 'ads');
        Setting::set('rewarded_ad_max_per_hour', '100', 'ads');
        Setting::set('rewarded_ad_daily_limit', '100', 'ads');
    }

    public function test_happy_path_credits_coins_and_returns_balance(): void
    {
        $user = $this->makeUser();

        $response = $this->claimAs($user, $this->demoSlug);

        $response->assertOk();
        $response->assertJson(['coins' => 5, 'balance' => 5]);

        $this->assertSame(5, Wallet::where('user_id', $user->id)->first()->coins);
        $this->assertTrue(
            CoinTransaction::where('user_id', $user->id)
                ->where('source', CoinTransaction::SOURCE_REWARDED_AD)
                ->exists()
        );
        $this->assertSame(1, AdReward::where('user_id', $user->id)->count());
    }

    public function test_client_timer_bypass_fails_on_server_min_interval(): void
    {
        $user = $this->makeUser();

        $this->claimAs($user, $this->demoSlug)->assertOk();

        // A cheater hammering the endpoint right after the countdown.
        $response = $this->claimAs($user, $this->demoSlug);

        $response->assertStatus(429);
        $this->assertSame(1, AdReward::where('user_id', $user->id)->count());
        $this->assertSame(5, Wallet::where('user_id', $user->id)->first()->coins);
    }

    public function test_daily_limit_is_enforced(): void
    {
        $this->relaxLimits();
        Setting::set('rewarded_ad_daily_limit', '2', 'ads');

        $user = $this->makeUser();
        $placement = \App\Models\AdPlacement::where('slug', $this->demoSlug)->firstOrFail();

        foreach (range(1, 2) as $n) {
            AdReward::create([
                'user_id' => $user->id,
                'placement_id' => $placement->id,
                'coins' => 5,
                'idempotency_key' => "seeded:{$user->id}:{$n}",
            ]);
        }

        $this->claimAs($user, $this->demoSlug)->assertStatus(429);
        $this->assertSame(2, AdReward::where('user_id', $user->id)->count());
    }

    public function test_hourly_velocity_is_enforced(): void
    {
        $this->relaxLimits();
        Setting::set('rewarded_ad_max_per_hour', '2', 'ads');

        $user = $this->makeUser();
        $placement = \App\Models\AdPlacement::where('slug', $this->demoSlug)->firstOrFail();

        foreach (range(1, 2) as $n) {
            $reward = AdReward::create([
                'user_id' => $user->id,
                'placement_id' => $placement->id,
                'coins' => 5,
                'idempotency_key' => "seeded-hour:{$user->id}:{$n}",
            ]);
            $reward->created_at = now()->subMinutes(30);
            $reward->save();
        }

        $this->claimAs($user, $this->demoSlug)->assertStatus(429);
    }

    public function test_shared_device_is_blocked(): void
    {
        $this->relaxLimits();
        $placement = \App\Models\AdPlacement::where('slug', $this->demoSlug)->firstOrFail();
        $placement->update(['frequency_cap_per_session' => 100]);

        // Four different users earning from the same device/IP is fine…
        foreach (range(1, 4) as $i) {
            $u = $this->makeUser();
            $this->claimAs($u, $this->demoSlug, '9.9.9.9', 'SharedAgent/1.0')->assertOk();
        }

        // …but the fifth account on that device is blocked.
        $fifth = $this->makeUser();
        $response = $this->claimAs($fifth, $this->demoSlug, '9.9.9.9', 'SharedAgent/1.0');

        $response->assertStatus(429);
        $this->assertSame(0, AdReward::where('user_id', $fifth->id)->count());
    }

    public function test_session_frequency_cap_applies_to_claims(): void
    {
        $this->relaxLimits();
        $user = $this->makeUser();

        // Pretend this session already saw the ad 5 times (cap = 5).
        $placement = \App\Models\AdPlacement::where('slug', $this->demoSlug)->firstOrFail();
        $this->withSession([\App\Services\AdService::SESSION_CAP_KEY => [$placement->id => 5]]);

        $this->claimAs($user, $this->demoSlug)->assertStatus(429);
    }

    public function test_disabled_placement_is_rejected(): void
    {
        $user = $this->makeUser();
        $placement = $this->makeRewardedPlacement();
        $placement->update(['enabled' => false]);

        $this->claimAs($user, $placement->slug)->assertStatus(403);
    }

    public function test_non_rewarded_placement_is_rejected(): void
    {
        $user = $this->makeUser();
        $placement = $this->makePlacement(); // banner

        $this->claimAs($user, $placement->slug)->assertStatus(403);
    }

    public function test_unknown_placement_is_404(): void
    {
        $user = $this->makeUser();

        $this->claimAs($user, 'no-such-ad')->assertNotFound();
    }

    public function test_guest_is_redirected_to_login(): void
    {
        $this->post(route('ads.reward', $this->demoSlug))->assertRedirect('/login');
    }

    public function test_repeat_claim_uses_unique_idempotency_keys(): void
    {
        $this->relaxLimits();
        $user = $this->makeUser();

        $this->claimAs($user, $this->demoSlug)->assertOk();
        $this->claimAs($user, $this->demoSlug)->assertOk();

        $keys = AdReward::where('user_id', $user->id)->pluck('idempotency_key');
        $this->assertCount(2, $keys);
        $this->assertCount(2, $keys->unique());
        $this->assertStringStartsWith("rewarded:{$user->id}:", $keys->first());
        $this->assertSame(10, Wallet::where('user_id', $user->id)->first()->coins);
    }

    public function test_existing_idempotency_key_returns_original_without_double_credit(): void
    {
        $this->relaxLimits();
        $user = $this->makeUser();
        $placement = \App\Models\AdPlacement::where('slug', $this->demoSlug)->firstOrFail();

        // Pre-create the exact key the service will compute for the first
        // claim of the day (n = 1): the row itself is backdated so today's
        // counter still starts at 1 and collides with it.
        $key = "rewarded:{$user->id}:{$placement->id}:" . today()->toDateString() . ':1';
        $seeded = AdReward::create([
            'user_id' => $user->id,
            'placement_id' => $placement->id,
            'coins' => 5,
            'idempotency_key' => $key,
        ]);
        $seeded->created_at = now()->subDay();
        $seeded->save();

        $response = $this->claimAs($user, $this->demoSlug);

        $response->assertOk();
        $response->assertJson(['coins' => 5]);
        // No new reward row, no new ledger credit.
        $this->assertSame(1, AdReward::where('user_id', $user->id)->count());
        $this->assertSame(0, CoinTransaction::where('user_id', $user->id)->count());
    }
}
