<?php

namespace Tests\Feature\Ads;

use App\Models\AdNetwork;
use App\Models\CoinTransaction;
use App\Models\Setting;
use App\Models\User;
use App\Services\Ads\AdmobSsvVerifier;
use App\Services\Ads\UnityS2sVerifier;
use Database\Seeders\AdSeeder;
use Database\Seeders\SettingSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Mobile-SDK ad networks (AdMob, Unity Ads): type registry, config
 * schemas, and the server-to-server reward verification hook.
 * Verifiers are stubbed — crypto is unit-tested through the stub seam.
 */
class AdNetworkTypesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(SettingSeeder::class);
        $this->seed(AdSeeder::class);
    }

    public function test_admob_and_unity_types_are_registered(): void
    {
        $this->assertContains(AdNetwork::TYPE_ADMOB, AdNetwork::TYPES);
        $this->assertContains(AdNetwork::TYPE_UNITY_ADS, AdNetwork::TYPES);
        $this->assertSame('Google AdMob (mobile SDK)', AdNetwork::typeLabel('admob'));
        $this->assertSame('Unity Ads (mobile SDK)', AdNetwork::typeLabel('unity_ads'));
    }

    public function test_config_schemas_expose_required_keys(): void
    {
        $admob = AdNetwork::configSchema('admob');
        $this->assertArrayHasKey('app_id', $admob);
        $this->assertArrayHasKey('rewarded_ad_unit_id', $admob);

        $unity = AdNetwork::configSchema('unity_ads');
        $this->assertArrayHasKey('game_id_android', $unity);
        $this->assertArrayHasKey('rewarded_placement_id', $unity);
        $this->assertArrayHasKey('rewarded_ad_unit_id', $unity);
        $this->assertArrayHasKey('banner_placement_id', $unity);
        $this->assertArrayHasKey('interstitial_placement_id', $unity);
        $this->assertArrayHasKey('s2s_secret', $unity);

        $this->assertSame([], AdNetwork::configSchema('adsense'));
    }

    public function test_unity_ids_are_seeded_as_defaults(): void
    {
        $unity = AdNetwork::where('slug', 'unity-ads')->firstOrFail();
        $config = $unity->config;

        $this->assertSame('800391724', $config['game_id_android']);
        $this->assertSame('BP_Rewarded_Android', $config['rewarded_placement_id']);
        $this->assertSame('a22cdead-1375-45a1-a611-10554ac4982c', $config['rewarded_ad_unit_id']);
        $this->assertSame('BP_Banner_Android', $config['banner_placement_id']);
        $this->assertSame('BP_Interstitial_Android', $config['interstitial_placement_id']);
    }

    public function test_seeder_preserves_owner_pasted_values(): void
    {
        $unity = AdNetwork::where('slug', 'unity-ads')->firstOrFail();
        $config = $unity->config;
        $config['s2s_secret'] = 'owner-secret-123';
        $unity->update(['config' => $config]);

        $this->seed(AdSeeder::class);

        $fresh = AdNetwork::where('slug', 'unity-ads')->firstOrFail()->config;
        $this->assertSame('owner-secret-123', $fresh['s2s_secret']);
        $this->assertSame('800391724', $fresh['game_id_android']);
    }

    public function test_mobile_sdk_flag(): void
    {
        $admob = AdNetwork::where('slug', 'admob')->firstOrFail();
        $unity = AdNetwork::where('slug', 'unity-ads')->firstOrFail();
        $adsense = AdNetwork::where('slug', 'adsense')->firstOrFail();

        $this->assertTrue($admob->isMobileSdk());
        $this->assertTrue($unity->isMobileSdk());
        $this->assertFalse($adsense->isMobileSdk());
    }

    public function test_seeded_mobile_networks_are_disabled_with_empty_ids(): void
    {
        foreach (['admob', 'unity-ads'] as $slug) {
            $network = AdNetwork::where('slug', $slug)->firstOrFail();
            $this->assertFalse($network->enabled);
        }
    }

    public function test_s2s_endpoint_403_for_disabled_network(): void
    {
        $response = $this->get('/ads/verify/admob?user_id=1');

        $response->assertForbidden();
    }

    public function test_s2s_endpoint_401_for_bad_signature(): void
    {
        $network = AdNetwork::where('slug', 'unity-ads')->firstOrFail();
        $network->update([
            'enabled' => true,
            'config' => ['s2s_secret' => 'topsecret'],
        ]);

        // Wrong HMAC → 401, nothing credited.
        $response = $this->get('/ads/verify/unity-ads?sid=tx1&oid=1&hmac=wrong');

        $response->assertUnauthorized();
        $response->assertJson(['status' => 'invalid_signature']);
    }

    public function test_s2s_endpoint_credits_on_valid_unity_callback(): void
    {
        $user = User::factory()->create(['email_verified_at' => now()]);
        $network = AdNetwork::where('slug', 'unity-ads')->firstOrFail();
        $network->update([
            'enabled' => true,
            'config' => ['s2s_secret' => 'topsecret'],
        ]);

        $params = ['oid' => (string) $user->id, 'reward_amount' => '7', 'sid' => 'unity-tx-1'];
        ksort($params);
        $hmac = hash_hmac('sha256', http_build_query($params), 'topsecret');

        $response = $this->get('/ads/verify/unity-ads?' . http_build_query($params + ['hmac' => $hmac]));

        $response->assertOk();
        $response->assertJson(['status' => 'credited']);

        $this->assertSame(7, $user->fresh()->coinBalance());
        $this->assertSame(1, CoinTransaction::where('user_id', $user->id)
            ->where('source', CoinTransaction::SOURCE_REWARDED_AD)
            ->count());

        // Retried callback (same transaction id) credits nothing more.
        $again = $this->get('/ads/verify/unity-ads?' . http_build_query($params + ['hmac' => $hmac]));
        $again->assertOk();
        $this->assertSame(7, $user->fresh()->coinBalance());
    }

    public function test_s2s_verifier_classes_exist_with_contract(): void
    {
        $this->assertInstanceOf(
            \App\Services\Ads\S2sVerifier::class,
            app(AdmobSsvVerifier::class)
        );
        $this->assertInstanceOf(
            \App\Services\Ads\S2sVerifier::class,
            app(UnityS2sVerifier::class)
        );
    }

    public function test_admob_verifier_rejects_unsigned_request_without_http(): void
    {
        $network = AdNetwork::where('slug', 'admob')->firstOrFail();
        $request = \Illuminate\Http\Request::create('/ads/verify/admob', 'GET', ['user_id' => '1']);

        $this->assertNull(app(AdmobSsvVerifier::class)->verify($network, $request));
    }
}
