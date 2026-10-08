<?php

namespace Tests\Feature\Offerwall;

use App\Models\CoinTransaction;
use App\Models\OfferwallConversion;
use App\Models\Wallet;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PostbackDedupeTest extends TestCase
{
    use RefreshDatabase;
    use CreatesOfferwalls;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpCreatesOfferwalls();
    }

    public function test_duplicate_postback_credits_only_once(): void
    {
        $provider = $this->makeProvider();
        $user = $this->makeUser();
        $click = $this->makeClick($provider, $user);

        $payload = [
            'provider_tx_id' => 'tx-dupe-1',
            'user_id' => $user->id,
            'payout' => 100,
            'click_uid' => $click->click_uid,
        ];

        $first = $this->signedPost($provider, $payload);
        $first->assertOk();
        $first->assertJsonPath('status', 'credited');

        // Same tx id retried (new signature over identical body).
        $second = $this->signedPost($provider, $payload);
        $second->assertOk();
        $second->assertJsonPath('status', 'duplicate');

        $this->assertSame(70, Wallet::where('user_id', $user->id)->first()->coins);
        $this->assertSame(
            1,
            CoinTransaction::where('idempotency_key', 'offerwall:test-provider:tx-dupe-1')->count()
        );
        $this->assertSame(1, OfferwallConversion::where('provider_tx_id', 'tx-dupe-1')->count());
    }

    public function test_duplicate_returns_200_so_providers_do_not_retry(): void
    {
        $provider = $this->makeProvider();
        $user = $this->makeUser();
        $click = $this->makeClick($provider, $user);

        $payload = [
            'provider_tx_id' => 'tx-dupe-2',
            'user_id' => $user->id,
            'payout' => 100,
            'click_uid' => $click->click_uid,
        ];

        $this->signedPost($provider, $payload)->assertOk();
        $retry = $this->signedPost($provider, $payload);

        $retry->assertOk();
        $retry->assertJsonPath('status', 'duplicate');
    }
}
