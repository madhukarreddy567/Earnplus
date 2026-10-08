<?php

namespace Tests\Feature\Offerwall;

use App\Models\OfferwallConversion;
use App\Models\OfferwallProvider;
use Database\Seeders\OfferwallSeeder;
use Database\Seeders\SettingSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Provider-specific postback intake: GET query params, per-provider
 * parameter mapping, chargebacks and the AdGem v2 signature scheme.
 * Additive — the JSON+X-Signature path is covered by the existing
 * Postback* tests and is untouched.
 */
class PostbackIntakeTest extends TestCase
{
    use RefreshDatabase;
    use CreatesOfferwalls;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpCreatesOfferwalls();
    }

    protected function enable(string $slug): OfferwallProvider
    {
        $provider = OfferwallProvider::where('slug', $slug)->firstOrFail();
        $provider->update(['enabled' => true]);

        return $provider->fresh();
    }

    public function test_get_postback_with_query_params_credits_via_param_map(): void
    {
        $provider = $this->enable('adgate'); // signature: none (documented)
        $user = $this->makeUser();
        $this->makeClick($provider, $user);

        // AdGate's real parameter names (verified from official example).
        $response = $this->getJson('/postback/adgate?' . http_build_query([
            'tx_id' => 'AG-1',
            'user_id' => $user->id,
            'points' => 120,
            'status' => 1,
        ]));

        $response->assertOk();
        $this->assertSame('credited', $response->json('status'));
        $this->assertSame(84, $response->json('user_coins')); // 70% of 120

        $conversion = OfferwallConversion::where('provider_tx_id', 'AG-1')->firstOrFail();
        $this->assertSame($user->id, $conversion->user_id);
        $this->assertSame(120, $conversion->payout_coins);
    }

    public function test_adgate_chargeback_is_logged_and_never_credited(): void
    {
        $provider = $this->enable('adgate');
        $user = $this->makeUser();
        $this->makeClick($provider, $user);

        // First the conversion credits normally...
        $this->getJson('/postback/adgate?' . http_build_query([
            'tx_id' => 'AG-2', 'user_id' => $user->id, 'points' => 100, 'status' => 1,
        ]))->assertOk();
        $before = $user->fresh()->coinBalance();

        // ...then AdGate reports a chargeback (status=0).
        $response = $this->getJson('/postback/adgate?' . http_build_query([
            'tx_id' => 'AG-2', 'user_id' => $user->id, 'status' => 0,
        ]));

        $response->assertOk();
        $this->assertSame('chargeback', $response->json('status'));
        $this->assertSame($before, $user->fresh()->coinBalance());

        $conversion = OfferwallConversion::where('provider_tx_id', 'AG-2')->firstOrFail();
        $this->assertTrue($conversion->meta['chargeback']);
    }

    public function test_adgem_v2_verifier_is_checked(): void
    {
        $provider = $this->enable('adgem');
        $provider->update(['postback_secret' => 'adgem-postback-key']);
        $user = $this->makeUser();
        $this->makeClick($provider, $user);

        $params = [
            'amount' => '1.00',
            'campaign_id' => '9',
            'payout' => 200,
            'player_id' => (string) $user->id,
            'transaction_id' => 'ADGEM-1',
        ];
        $params['request_id'] = 'req-1';
        $params['verifier'] = $this->adgemVerifier($provider, $params);

        $response = $this->getJson('/postback/adgem?' . http_build_query($params));

        $response->assertOk();
        $this->assertSame('credited', $response->json('status'));
        $this->assertSame(140, $response->json('user_coins')); // 70% of 200
    }

    public function test_adgem_v2_tampered_verifier_is_rejected(): void
    {
        $provider = $this->enable('adgem');
        $provider->update(['postback_secret' => 'adgem-postback-key']);
        $user = $this->makeUser();
        $this->makeClick($provider, $user);

        $response = $this->getJson('/postback/adgem?' . http_build_query([
            'payout' => 200,
            'player_id' => (string) $user->id,
            'transaction_id' => 'ADGEM-2',
            'verifier' => 'tampered',
        ]));

        $response->assertStatus(401);
        $this->assertSame('invalid_signature', $response->json('status'));
    }

    public function test_unknown_provider_still_404s(): void
    {
        $this->getJson('/postback/nope?tx_id=1')->assertStatus(404);
    }

    /**
     * Mirror of the controller's v2 canonicalization: alphabetically
     * sorted query string (excluding verifier, request_id, sig),
     * HMAC-SHA256 with the postback secret.
     */
    protected function adgemVerifier(OfferwallProvider $provider, array $params): string
    {
        unset($params['verifier'], $params['request_id'], $params['sig']);
        ksort($params);

        $canonical = implode('&', array_map(
            fn ($k, $v) => $k . '=' . $v,
            array_keys($params),
            array_values($params)
        ));

        return hash_hmac('sha256', $canonical, $provider->postback_secret);
    }
}
