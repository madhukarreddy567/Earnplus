<?php

namespace Tests\Feature\Offerwall;

use App\Models\OfferwallClick;
use App\Models\OfferwallConversion;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PostbackValidationTest extends TestCase
{
    use RefreshDatabase;
    use CreatesOfferwalls;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpCreatesOfferwalls();
    }

    public function test_conversion_without_any_click_is_rejected(): void
    {
        $provider = $this->makeProvider();
        $user = $this->makeUser();

        $response = $this->signedPost($provider, [
            'provider_tx_id' => 'tx-noclick',
            'user_id' => $user->id,
            'payout' => 100,
        ]);

        $response->assertOk();
        $response->assertJsonPath('status', 'rejected');

        $this->assertDatabaseHas('offerwall_conversions', [
            'provider_tx_id' => 'tx-noclick',
            'status' => OfferwallConversion::STATUS_REJECTED,
            'user_coins' => 0,
        ]);
    }

    public function test_conversion_with_expired_click_is_rejected(): void
    {
        $provider = $this->makeProvider();
        $user = $this->makeUser();
        $click = $this->makeClick($provider, $user);
        $click->update(['expires_at' => now()->subMinute()]);

        $response = $this->signedPost($provider, [
            'provider_tx_id' => 'tx-expired',
            'user_id' => $user->id,
            'payout' => 100,
            'click_uid' => $click->click_uid,
        ]);

        $response->assertOk();
        $response->assertJsonPath('status', 'rejected');
        $this->assertDatabaseMissing('coin_transactions', [
            'idempotency_key' => 'offerwall:test-provider:tx-expired',
        ]);
    }

    public function test_conversion_with_click_from_another_user_is_rejected(): void
    {
        $provider = $this->makeProvider();
        $user = $this->makeUser();
        $other = $this->makeUser();
        $click = $this->makeClick($provider, $other);

        $response = $this->signedPost($provider, [
            'provider_tx_id' => 'tx-wronguser',
            'user_id' => $user->id,
            'payout' => 100,
            'click_uid' => $click->click_uid,
        ]);

        $response->assertOk();
        $response->assertJsonPath('status', 'rejected');
    }

    public function test_invalid_payload_is_rejected_and_logged(): void
    {
        $provider = $this->makeProvider();
        $user = $this->makeUser();

        $response = $this->signedPost($provider, [
            'provider_tx_id' => '',
            'user_id' => $user->id,
            'payout' => 0,
        ]);

        $response->assertOk();
        $response->assertJsonPath('status', 'rejected');

        // Raw payload is always logged, even for rejections.
        $conversion = OfferwallConversion::latest()->first();
        $this->assertSame(OfferwallConversion::STATUS_REJECTED, $conversion->status);
        $this->assertNotNull($conversion->raw_payload);
    }

    public function test_unknown_user_is_rejected(): void
    {
        $provider = $this->makeProvider();

        $response = $this->signedPost($provider, [
            'provider_tx_id' => 'tx-nouser',
            'user_id' => 999999,
            'payout' => 100,
        ]);

        $response->assertOk();
        $response->assertJsonPath('status', 'rejected');
    }

    public function test_consumed_click_cannot_convert_twice(): void
    {
        $provider = $this->makeProvider();
        $user = $this->makeUser();
        $click = $this->makeClick($provider, $user);

        $this->signedPost($provider, [
            'provider_tx_id' => 'tx-first',
            'user_id' => $user->id,
            'payout' => 100,
            'click_uid' => $click->click_uid,
        ])->assertJsonPath('status', 'credited');

        // Same click, different tx id: the click is already consumed.
        $retry = $this->signedPost($provider, [
            'provider_tx_id' => 'tx-second',
            'user_id' => $user->id,
            'payout' => 100,
            'click_uid' => $click->click_uid,
        ]);

        $retry->assertOk();
        $retry->assertJsonPath('status', 'rejected');
        $this->assertSame(OfferwallClick::STATUS_CONVERTED, $click->fresh()->status);
    }
}
