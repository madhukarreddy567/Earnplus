<?php

namespace Tests\Feature\Offerwall;

use App\Models\OfferwallConversion;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PostbackIpWhitelistTest extends TestCase
{
    use RefreshDatabase;
    use CreatesOfferwalls;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpCreatesOfferwalls();
    }

    public function test_whitelisted_ip_is_allowed(): void
    {
        $provider = $this->makeProvider(['ip_whitelist' => "127.0.0.1\n203.0.113.9"]);
        $user = $this->makeUser();
        $click = $this->makeClick($provider, $user);

        $response = $this->signedPost($provider, [
            'provider_tx_id' => 'tx-ip-1',
            'user_id' => $user->id,
            'payout' => 50,
            'click_uid' => $click->click_uid,
        ]);

        $response->assertOk();
        $response->assertJsonPath('status', 'credited');
    }

    public function test_non_whitelisted_ip_is_blocked_with_403(): void
    {
        $provider = $this->makeProvider(['ip_whitelist' => '203.0.113.9']);
        $user = $this->makeUser();

        $response = $this->signedPost($provider, [
            'provider_tx_id' => 'tx-ip-2',
            'user_id' => $user->id,
            'payout' => 50,
        ]);

        $response->assertStatus(403);
        $response->assertJsonPath('status', 'ip_blocked');
        $this->assertDatabaseMissing('offerwall_conversions', ['provider_tx_id' => 'tx-ip-2']);
    }

    public function test_cidr_whitelist_entry_is_honoured(): void
    {
        $provider = $this->makeProvider(['ip_whitelist' => '127.0.0.0/24']);
        $user = $this->makeUser();
        $click = $this->makeClick($provider, $user);

        $response = $this->signedPost($provider, [
            'provider_tx_id' => 'tx-ip-3',
            'user_id' => $user->id,
            'payout' => 50,
            'click_uid' => $click->click_uid,
        ]);

        $response->assertOk();
        $response->assertJsonPath('status', 'credited');
    }

    public function test_empty_whitelist_allows_any_signed_postback(): void
    {
        $provider = $this->makeProvider(['ip_whitelist' => null]);
        $user = $this->makeUser();
        $click = $this->makeClick($provider, $user);

        $response = $this->signedPost($provider, [
            'provider_tx_id' => 'tx-ip-4',
            'user_id' => $user->id,
            'payout' => 50,
            'click_uid' => $click->click_uid,
        ]);

        $response->assertOk();
        $this->assertDatabaseHas('offerwall_conversions', [
            'provider_tx_id' => 'tx-ip-4',
            'status' => OfferwallConversion::STATUS_CREDITED,
        ]);
    }
}
