<?php

namespace Tests\Feature\Promotions;

use App\Models\AdPlacement;
use App\Models\CoinTransaction;
use App\Models\OfferwallProvider;
use App\Models\Promotion;
use App\Models\Setting;
use App\Models\User;
use App\Services\CheckinService;
use App\Services\PostbackService;
use App\Services\ReferralService;
use App\Services\RewardedAdService;
use App\Services\SpinService;
use Database\Seeders\AdSeeder;
use Database\Seeders\OfferwallSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Promotions hook into every earning path: the multiplied amount is
 * credited and the transaction meta records the promotion ids.
 * With no active promotion, amounts are identical to before.
 */
class PromotionEarningTest extends TestCase
{
    use RefreshDatabase;
    use CreatesPromotions;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpCreatesPromotions();
        $this->seed(OfferwallSeeder::class);
        $this->seed(AdSeeder::class);
    }

    public function test_spin_win_is_multiplied_and_meta_records_promotions(): void
    {
        Setting::set('spin_win_probability', '100', 'spin');
        Setting::set('spin_min_coins', '100', 'spin');
        Setting::set('spin_max_coins', '100', 'spin');

        $promo = $this->makePromotion(['multiplier' => 2.00, 'scope' => Promotion::SCOPE_SPIN]);

        $user = User::factory()->create(['email_verified_at' => now()]);
        $result = app(SpinService::class)->spin($user);

        $this->assertTrue($result['won']);
        $this->assertSame(200, $result['amount']);
        $this->assertSame(200, $user->coinBalance());

        $tx = CoinTransaction::where('user_id', $user->id)->latest('id')->first();
        $this->assertSame([$promo->id], $tx->meta['promotion_ids']);
        $this->assertSame(100, $tx->meta['base_amount']);
    }

    public function test_spin_without_promotion_is_unchanged(): void
    {
        Setting::set('spin_win_probability', '100', 'spin');
        Setting::set('spin_min_coins', '100', 'spin');
        Setting::set('spin_max_coins', '100', 'spin');

        $user = User::factory()->create(['email_verified_at' => now()]);
        $result = app(SpinService::class)->spin($user);

        $this->assertSame(100, $result['amount']);

        $tx = CoinTransaction::where('user_id', $user->id)->latest('id')->first();
        $this->assertArrayNotHasKey('promotion_ids', $tx->meta ?? []);
    }

    public function test_checkin_payout_is_multiplied(): void
    {
        $promo = $this->makePromotion(['multiplier' => 2.00, 'scope' => Promotion::SCOPE_DAILY_CHECKIN]);

        $user = User::factory()->create(['email_verified_at' => now()]);
        $result = app(CheckinService::class)->checkin($user);

        // Default check-in pays 10 coins → 20 with the 2x promo.
        $this->assertSame(20, $result['amount']);
        $this->assertSame(20, $user->coinBalance());
        $this->assertSame([$promo->id], $result['transaction']->meta['promotion_ids']);
    }

    public function test_referral_bonus_is_multiplied(): void
    {
        $promo = $this->makePromotion(['multiplier' => 2.00, 'scope' => Promotion::SCOPE_REFERRAL_BONUS]);

        $referrer = User::factory()->create(['email_verified_at' => now()]);
        $newUser = User::factory()->create(['email_verified_at' => now()]);

        $tx = app(ReferralService::class)->rewardReferrer($newUser, $referrer);

        // Default referral bonus is 25 coins → 50 with the 2x promo.
        $this->assertSame(50, $tx->amount);
        $this->assertSame([$promo->id], $tx->meta['promotion_ids']);
        $this->assertSame(25, $tx->meta['base_amount']);
    }

    public function test_offerwall_conversion_is_multiplied(): void
    {
        $provider = OfferwallProvider::where('slug', 'demo')->firstOrFail();
        $provider->update(['user_revenue_share' => 100]);

        $promo = $this->makePromotion(['multiplier' => 2.00, 'scope' => Promotion::SCOPE_TASK_OFFERWALL]);

        $user = User::factory()->create(['email_verified_at' => now()]);
        $click = app(\App\Services\OfferwallService::class)->trackClick($provider, $user, '127.0.0.1', 'TestAgent/1.0');

        $result = app(PostbackService::class)->handle($provider, [
            'provider_tx_id' => 'promo-tx-1',
            'user_id' => $user->id,
            'payout' => 100,
            'click_uid' => $click->click_uid,
        ], '127.0.0.1', 'TestAgent/1.0');

        $this->assertSame('credited', $result['status']);
        $this->assertSame(200, $result['user_coins']);
        $this->assertSame(200, $user->coinBalance());

        $tx = CoinTransaction::where('user_id', $user->id)->latest('id')->first();
        $this->assertSame([$promo->id], $tx->meta['promotion_ids']);
    }

    public function test_rewarded_ad_claim_is_multiplied(): void
    {
        Setting::set('rewarded_ad_min_interval_seconds', '0', 'ads');
        Setting::set('rewarded_ad_max_per_hour', '100', 'ads');
        Setting::set('rewarded_ad_daily_limit', '100', 'ads');

        $promo = $this->makePromotion(['multiplier' => 3.00, 'scope' => Promotion::SCOPE_REWARDED_AD]);

        $placement = AdPlacement::where('placement_type', 'rewarded')->where('enabled', true)->firstOrFail();
        $baseCoins = (int) $placement->coins;

        $user = User::factory()->create(['email_verified_at' => now()]);
        $this->actingAs($user);

        $response = $this->call(
            'POST',
            route('ads.reward', $placement->slug),
            [],
            [],
            [],
            ['REMOTE_ADDR' => '127.0.0.1', 'HTTP_USER_AGENT' => 'TestAgent/1.0', 'HTTP_ACCEPT' => 'application/json']
        );

        $response->assertOk();
        $expected = (int) floor($baseCoins * 3);
        $this->assertSame($expected, $response->json('coins'));
        $this->assertSame($expected, $user->coinBalance());

        $tx = CoinTransaction::where('user_id', $user->id)->latest('id')->first();
        $this->assertSame([$promo->id], $tx->meta['promotion_ids']);
    }

    public function test_existing_earning_amounts_unchanged_without_promotions(): void
    {
        // Regression: the full no-promotion path must behave exactly as before.
        Setting::set('spin_win_probability', '100', 'spin');
        Setting::set('spin_min_coins', '100', 'spin');
        Setting::set('spin_max_coins', '100', 'spin');

        $user = User::factory()->create(['email_verified_at' => now()]);

        app(SpinService::class)->spin($user);
        app(CheckinService::class)->checkin($user);

        $referrer = User::factory()->create(['email_verified_at' => now()]);
        $newUser = User::factory()->create(['email_verified_at' => now()]);
        app(ReferralService::class)->rewardReferrer($newUser, $referrer);

        $this->assertSame(110, $user->coinBalance()); // 100 spin + 10 check-in
        $this->assertSame(25, $referrer->coinBalance()); // referral bonus untouched

        $withPromos = CoinTransaction::whereIn('user_id', [$user->id, $referrer->id])
            ->get()
            ->filter(fn ($tx) => ! empty($tx->meta['promotion_ids'] ?? null));
        $this->assertSame(0, $withPromos->count());
    }
}
