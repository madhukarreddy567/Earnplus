<?php

namespace Tests\Feature\Offerwall;

use App\Models\Wallet;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RevenueShareTest extends TestCase
{
    use RefreshDatabase;
    use CreatesOfferwalls;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpCreatesOfferwalls();
    }

    public function test_user_gets_revenue_share_of_payout(): void
    {
        $provider = $this->makeProvider(['user_revenue_share' => 70.00]);
        $user = $this->makeUser();
        $click = $this->makeClick($provider, $user);

        $this->signedPost($provider, [
            'provider_tx_id' => 'tx-share-1',
            'user_id' => $user->id,
            'payout' => 100,
            'click_uid' => $click->click_uid,
        ])->assertJsonPath('user_coins', 70);

        $this->assertSame(70, Wallet::where('user_id', $user->id)->first()->coins);
    }

    public function test_full_share_gives_everything(): void
    {
        $provider = $this->makeProvider(['user_revenue_share' => 100.00]);
        $user = $this->makeUser();
        $click = $this->makeClick($provider, $user);

        $this->signedPost($provider, [
            'provider_tx_id' => 'tx-share-2',
            'user_id' => $user->id,
            'payout' => 250,
            'click_uid' => $click->click_uid,
        ])->assertJsonPath('user_coins', 250);
    }

    public function test_fractional_share_rounds_down(): void
    {
        $provider = $this->makeProvider(['user_revenue_share' => 33.33]);
        $user = $this->makeUser();
        $click = $this->makeClick($provider, $user);

        // 33.33% of 100 = 33.33 → 33 coins.
        $this->signedPost($provider, [
            'provider_tx_id' => 'tx-share-3',
            'user_id' => $user->id,
            'payout' => 100,
            'click_uid' => $click->click_uid,
        ])->assertJsonPath('user_coins', 33);
    }

    public function test_tiny_payout_still_gives_at_least_one_coin(): void
    {
        $provider = $this->makeProvider(['user_revenue_share' => 10.00]);
        $user = $this->makeUser();
        $click = $this->makeClick($provider, $user);

        $this->signedPost($provider, [
            'provider_tx_id' => 'tx-share-4',
            'user_id' => $user->id,
            'payout' => 1,
            'click_uid' => $click->click_uid,
        ])->assertJsonPath('user_coins', 1);
    }
}
