<?php

namespace Tests\Feature\Promotions;

use App\Models\Promotion;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PromotionUiTest extends TestCase
{
    use RefreshDatabase;
    use CreatesPromotions;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpCreatesPromotions();
    }

    public function test_dashboard_shows_promo_banner_with_countdown_when_active(): void
    {
        $endsAt = now()->addDays(2)->addHours(3);
        $this->makePromotion([
            'banner_title' => 'Festival Blast — 2X coins!',
            'banner_subtitle' => 'Everything pays double',
            'badge_text' => '2X',
            'ends_at' => $endsAt,
        ]);

        $this->verifiedUser();

        $response = $this->get(route('dashboard'));

        $response->assertOk();
        $response->assertSee('Festival Blast — 2X coins!');
        $response->assertSee('Everything pays double');
        $response->assertSee('data-countdown-to="' . $endsAt->toIso8601String() . '"', false);
    }

    public function test_dashboard_hides_banner_when_no_promotion_active(): void
    {
        $this->verifiedUser();

        $response = $this->get(route('dashboard'));

        $response->assertOk();
        $response->assertDontSee('ep-promo-banner');
        $response->assertDontSee('data-countdown-to');
    }

    public function test_dashboard_tiles_show_promo_badges(): void
    {
        $this->makePromotion([
            'scope' => Promotion::SCOPE_SPIN,
            'badge_text' => '3X',
            'multiplier' => 3.00,
        ]);

        $this->verifiedUser();

        $response = $this->get(route('dashboard'));

        $response->assertOk();
        // The spin tile carries the badge; other tiles do not.
        $response->assertSee('ep-qa-badge-promo');
        $response->assertSee('3X');
    }

    public function test_tasks_page_shows_banner_and_provider_badges(): void
    {
        $this->seed(\Database\Seeders\OfferwallSeeder::class);

        $this->makePromotion([
            'scope' => Promotion::SCOPE_TASK_OFFERWALL,
            'badge_text' => '2X',
            'banner_title' => 'Task week — double coins!',
        ]);

        $this->verifiedUser();

        $response = $this->get(route('tasks'));

        $response->assertOk();
        $response->assertSee('Task week — double coins!');
        $response->assertSee('2X');
    }

    public function test_disabled_promotion_shows_no_banner_or_badges(): void
    {
        $this->makePromotion(['enabled' => false, 'banner_title' => 'Invisible promo']);

        $this->verifiedUser();

        $response = $this->get(route('dashboard'));

        $response->assertOk();
        $response->assertDontSee('Invisible promo');
        $response->assertDontSee('ep-promo-banner');
    }
}
