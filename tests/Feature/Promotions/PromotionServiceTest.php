<?php

namespace Tests\Feature\Promotions;

use App\Models\Promotion;
use App\Models\Setting;
use App\Services\PromotionService;
use Database\Seeders\SettingSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PromotionServiceTest extends TestCase
{
    use RefreshDatabase;
    use CreatesPromotions;

    protected PromotionService $promotions;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpCreatesPromotions();
        $this->promotions = app(PromotionService::class);
    }

    public function test_no_promotions_passes_amount_through_untouched(): void
    {
        $result = $this->promotions->applyMultipliers(100, Promotion::SCOPE_SPIN);

        $this->assertSame(100, $result['coins']);
        $this->assertTrue($result['promotions']->isEmpty());
    }

    public function test_global_promotion_doubles_every_scope(): void
    {
        $this->makePromotion(['multiplier' => 2.00, 'scope' => Promotion::SCOPE_GLOBAL]);

        foreach ([Promotion::SCOPE_SPIN, Promotion::SCOPE_TASK_OFFERWALL, Promotion::SCOPE_DAILY_CHECKIN] as $scope) {
            $result = $this->promotions->applyMultipliers(100, $scope);
            $this->assertSame(200, $result['coins'], "scope {$scope}");
            $this->assertCount(1, $result['promotions']);
        }
    }

    public function test_scoped_promotion_does_not_touch_other_scopes(): void
    {
        $this->makePromotion(['multiplier' => 3.00, 'scope' => Promotion::SCOPE_SPIN]);

        $spin = $this->promotions->applyMultipliers(100, Promotion::SCOPE_SPIN);
        $this->assertSame(300, $spin['coins']);

        $tasks = $this->promotions->applyMultipliers(100, Promotion::SCOPE_TASK_OFFERWALL);
        $this->assertSame(100, $tasks['coins']);
        $this->assertTrue($tasks['promotions']->isEmpty());
    }

    public function test_fractional_multiplier_rounds_down(): void
    {
        $this->makePromotion(['multiplier' => 1.50, 'badge_text' => '1.5X']);

        // 101 * 1.5 = 151.5 → 151, never a fraction.
        $result = $this->promotions->applyMultipliers(101, Promotion::SCOPE_GLOBAL);
        $this->assertSame(151, $result['coins']);
    }

    public function test_disabled_promotion_never_applies(): void
    {
        $this->makePromotion(['multiplier' => 2.00, 'enabled' => false]);

        $result = $this->promotions->applyMultipliers(100, Promotion::SCOPE_GLOBAL);
        $this->assertSame(100, $result['coins']);
        $this->assertTrue($result['promotions']->isEmpty());
    }

    public function test_not_yet_started_promotion_does_not_apply(): void
    {
        $this->makePromotion([
            'starts_at' => now()->addHour(),
            'ends_at' => now()->addHours(3),
        ]);

        $this->assertSame(100, $this->promotions->applyMultipliers(100, Promotion::SCOPE_GLOBAL)['coins']);
    }

    public function test_ended_promotion_does_not_apply(): void
    {
        $this->makePromotion([
            'starts_at' => now()->subHours(3),
            'ends_at' => now()->subHour(),
        ]);

        $this->assertSame(100, $this->promotions->applyMultipliers(100, Promotion::SCOPE_GLOBAL)['coins']);
    }

    public function test_best_only_picks_highest_multiplier(): void
    {
        $this->makePromotion(['multiplier' => 2.00, 'priority' => 0]);
        $this->makePromotion(['multiplier' => 3.00, 'priority' => 5]);

        $result = $this->promotions->applyMultipliers(100, Promotion::SCOPE_GLOBAL);
        $this->assertSame(300, $result['coins']);
    }

    public function test_multiply_stacking_combines_and_caps(): void
    {
        Setting::set('promotion_stacking', 'multiply', 'promotions');
        Setting::set('promotion_max_stacked_multiplier', '5.0', 'promotions');

        $this->makePromotion(['multiplier' => 2.00, 'priority' => 0]);
        $this->makePromotion(['multiplier' => 3.00, 'priority' => 1]);

        // 2x * 3x = 6x, capped at 5x → 500.
        $result = $this->promotions->applyMultipliers(100, Promotion::SCOPE_GLOBAL);
        $this->assertSame(500, $result['coins']);
    }

    public function test_multiply_stacking_below_cap(): void
    {
        Setting::set('promotion_stacking', 'multiply', 'promotions');

        $this->makePromotion(['multiplier' => 1.50, 'priority' => 0]);
        $this->makePromotion(['multiplier' => 2.00, 'priority' => 1]);

        // 1.5 * 2 = 3x → 300.
        $result = $this->promotions->applyMultipliers(100, Promotion::SCOPE_GLOBAL);
        $this->assertSame(300, $result['coins']);
    }

    public function test_provider_specific_promotion_only_applies_to_that_provider(): void
    {
        $providerA = \App\Models\OfferwallProvider::create([
            'name' => 'Wall A', 'slug' => 'wall-a', 'enabled' => true,
            'postback_secret' => 'secret', 'user_revenue_share' => 100,
        ]);
        $providerB = \App\Models\OfferwallProvider::create([
            'name' => 'Wall B', 'slug' => 'wall-b', 'enabled' => true,
            'postback_secret' => 'secret', 'user_revenue_share' => 100,
        ]);

        $this->makePromotion([
            'multiplier' => 2.00,
            'scope' => Promotion::SCOPE_TASK_OFFERWALL,
            'provider_id' => $providerA->id,
        ]);

        $forA = $this->promotions->applyMultipliers(100, Promotion::SCOPE_TASK_OFFERWALL, $providerA->id);
        $this->assertSame(200, $forA['coins']);

        $forB = $this->promotions->applyMultipliers(100, Promotion::SCOPE_TASK_OFFERWALL, $providerB->id);
        $this->assertSame(100, $forB['coins']);
    }

    public function test_generic_task_promotion_applies_to_any_provider(): void
    {
        $provider = \App\Models\OfferwallProvider::create([
            'name' => 'Wall', 'slug' => 'wall', 'enabled' => true,
            'postback_secret' => 'secret', 'user_revenue_share' => 100,
        ]);

        $this->makePromotion([
            'multiplier' => 2.00,
            'scope' => Promotion::SCOPE_TASK_OFFERWALL,
            'provider_id' => null,
        ]);

        $result = $this->promotions->applyMultipliers(100, Promotion::SCOPE_TASK_OFFERWALL, $provider->id);
        $this->assertSame(200, $result['coins']);
    }

    public function test_badge_for_returns_strongest_multiplier_badge(): void
    {
        $this->makePromotion(['multiplier' => 2.00, 'badge_text' => '2X', 'scope' => Promotion::SCOPE_SPIN]);
        $this->makePromotion(['multiplier' => 3.00, 'badge_text' => '3X', 'scope' => Promotion::SCOPE_SPIN]);

        $this->assertSame('3X', $this->promotions->badgeFor(Promotion::SCOPE_SPIN));
    }

    public function test_badge_for_returns_null_when_nothing_active(): void
    {
        $this->assertNull($this->promotions->badgeFor(Promotion::SCOPE_SPIN));
    }

    public function test_badge_text_derived_from_multiplier_when_blank(): void
    {
        $promo = $this->makePromotion(['badge_text' => null, 'multiplier' => 1.50]);
        $this->assertSame('1.5X', $promo->badgeText());

        $promo2 = $this->makePromotion(['badge_text' => null, 'multiplier' => 3.00]);
        $this->assertSame('3X', $promo2->badgeText());
    }

    public function test_banner_for_returns_highest_priority_live_promotion(): void
    {
        $this->makePromotion(['name' => 'Low prio', 'priority' => 5]);
        $high = $this->makePromotion(['name' => 'High prio', 'priority' => 0]);

        $this->assertSame($high->id, $this->promotions->bannerFor(Promotion::SCOPE_GLOBAL)->id);
    }

    public function test_banner_for_returns_null_when_nothing_active(): void
    {
        $this->assertNull($this->promotions->bannerFor(Promotion::SCOPE_GLOBAL));
    }
}
