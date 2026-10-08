<?php

namespace Tests\Feature\Offerwall;

use App\Models\OfferwallConversion;
use App\Models\Wallet;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PostbackSignatureTest extends TestCase
{
    use RefreshDatabase;
    use CreatesOfferwalls;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpCreatesOfferwalls();
    }

    public function test_valid_header_signature_credits_coins(): void
    {
        $provider = $this->makeProvider();
        $user = $this->makeUser();
        $click = $this->makeClick($provider, $user);

        $payload = [
            'provider_tx_id' => 'tx-1',
            'user_id' => $user->id,
            'payout' => 100,
            'click_uid' => $click->click_uid,
        ];

        $response = $this->signedPost($provider, $payload);

        $response->assertOk();
        $response->assertJsonPath('status', 'credited');
        $response->assertJsonPath('user_coins', 70);

        $this->assertSame(70, Wallet::where('user_id', $user->id)->first()->coins);
        $this->assertDatabaseHas('offerwall_conversions', [
            'provider_tx_id' => 'tx-1',
            'status' => OfferwallConversion::STATUS_CREDITED,
            'user_coins' => 70,
        ]);
    }

    public function test_sig_query_param_fallback_is_accepted(): void
    {
        $provider = $this->makeProvider();
        $user = $this->makeUser();
        $click = $this->makeClick($provider, $user);

        $payload = [
            'provider_tx_id' => 'tx-2',
            'user_id' => $user->id,
            'payout' => 100,
            'click_uid' => $click->click_uid,
        ];

        $rawBody = json_encode($payload);
        $sig = $this->sign($provider, $rawBody);

        $response = $this->postJson('/postback/' . $provider->slug . '?sig=' . $sig, $payload);

        $response->assertOk();
        $response->assertJsonPath('status', 'credited');
    }

    public function test_invalid_signature_is_rejected_with_401(): void
    {
        $provider = $this->makeProvider();
        $user = $this->makeUser();

        $response = $this->signedPost($provider, [
            'provider_tx_id' => 'tx-3',
            'user_id' => $user->id,
            'payout' => 100,
        ], 'wrong-signature');

        $response->assertStatus(401);
        $response->assertJsonPath('status', 'invalid_signature');
        $this->assertDatabaseMissing('offerwall_conversions', ['provider_tx_id' => 'tx-3']);
    }

    public function test_missing_signature_is_rejected_with_401(): void
    {
        $provider = $this->makeProvider();
        $user = $this->makeUser();

        $response = $this->postJson('/postback/' . $provider->slug, [
            'provider_tx_id' => 'tx-4',
            'user_id' => $user->id,
            'payout' => 100,
        ]);

        $response->assertStatus(401);
        $response->assertJsonPath('status', 'invalid_signature');
    }

    public function test_unknown_provider_returns_404(): void
    {
        $response = $this->postJson('/postback/no-such-provider', ['a' => 'b']);

        $response->assertNotFound();
    }

    public function test_disabled_provider_rejects_postbacks_with_403(): void
    {
        $provider = $this->makeProvider(['enabled' => false]);
        $user = $this->makeUser();

        $response = $this->signedPost($provider, [
            'provider_tx_id' => 'tx-5',
            'user_id' => $user->id,
            'payout' => 100,
        ]);

        $response->assertStatus(403);
        $response->assertJsonPath('status', 'provider_disabled');
        $this->assertDatabaseMissing('offerwall_conversions', ['provider_tx_id' => 'tx-5']);
    }
}
