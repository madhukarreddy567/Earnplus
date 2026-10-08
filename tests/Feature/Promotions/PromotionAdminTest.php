<?php

namespace Tests\Feature\Promotions;

use App\Models\Promotion;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PromotionAdminTest extends TestCase
{
    use RefreshDatabase;
    use CreatesPromotions;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpCreatesPromotions();
        $this->loginAsAdmin();
    }

    public function test_guests_cannot_open_promotions_admin(): void
    {
        auth('admin')->logout();

        $this->get(route('admin.promotions.index'))->assertRedirect(route('admin.login'));
    }

    public function test_admin_sees_grouped_promotion_list(): void
    {
        $this->makePromotion(['name' => 'Live One']);
        $this->makePromotion(['name' => 'Future One', 'starts_at' => now()->addDay(), 'ends_at' => now()->addDays(2)]);
        $this->makePromotion(['name' => 'Old One', 'starts_at' => now()->subDays(2), 'ends_at' => now()->subDay()]);
        $this->makePromotion(['name' => 'Off One', 'enabled' => false]);

        $response = $this->get(route('admin.promotions.index'));

        $response->assertOk();
        $response->assertSee('Live One');
        $response->assertSee('Future One');
        $response->assertSee('Old One');
        $response->assertSee('Off One');
        $response->assertSee('Active now');
        $response->assertSee('Upcoming');
        $response->assertSee('Expired');
        $response->assertSee('Disabled');
    }

    public function test_admin_can_create_a_promotion(): void
    {
        $response = $this->post(route('admin.promotions.store'), [
            'name' => 'Diwali Blast',
            'multiplier' => '2.5',
            'scope' => Promotion::SCOPE_GLOBAL,
            'starts_at' => now()->addDay()->format('Y-m-d\TH:i'),
            'ends_at' => now()->addDays(3)->format('Y-m-d\TH:i'),
            'banner_title' => 'Diwali Blast!',
            'badge_text' => '2.5X',
            'priority' => 1,
            'enabled' => '1',
        ]);

        $response->assertRedirect(route('admin.promotions.index'));

        $promo = Promotion::where('name', 'Diwali Blast')->firstOrFail();
        $this->assertSame('diwali-blast', $promo->slug);
        $this->assertSame(2.5, (float) $promo->multiplier);
        $this->assertTrue($promo->enabled);
    }

    public function test_admin_can_update_and_toggle_and_delete(): void
    {
        $promo = $this->makePromotion(['name' => 'Toggle Me']);

        $this->put(route('admin.promotions.update', $promo), [
            'name' => 'Toggle Me',
            'multiplier' => '3',
            'scope' => Promotion::SCOPE_SPIN,
            'starts_at' => now()->subHour()->format('Y-m-d\TH:i'),
            'ends_at' => now()->addHour()->format('Y-m-d\TH:i'),
            'priority' => 2,
            'enabled' => '1',
        ])->assertRedirect(route('admin.promotions.index'));

        $this->assertSame(Promotion::SCOPE_SPIN, $promo->fresh()->scope);

        $this->post(route('admin.promotions.toggle', $promo))->assertRedirect(route('admin.promotions.index'));
        $this->assertFalse($promo->fresh()->enabled);

        $this->post(route('admin.promotions.toggle', $promo));
        $this->assertTrue($promo->fresh()->enabled);

        $this->delete(route('admin.promotions.destroy', $promo))->assertRedirect(route('admin.promotions.index'));
        $this->assertNull(Promotion::find($promo->id));
    }

    public function test_validation_rejects_bad_multiplier_and_window(): void
    {
        $response = $this->post(route('admin.promotions.store'), [
            'name' => 'Bad Promo',
            'multiplier' => '1.05', // below the 1.1 minimum
            'scope' => Promotion::SCOPE_GLOBAL,
            'starts_at' => now()->addDays(3)->format('Y-m-d\TH:i'),
            'ends_at' => now()->addDay()->format('Y-m-d\TH:i'), // ends before it starts
            'enabled' => '1',
        ]);

        $response->assertSessionHasErrors(['multiplier', 'ends_at']);
        $this->assertNull(Promotion::where('name', 'Bad Promo')->first());
    }

    public function test_validation_rejects_multiplier_above_ten(): void
    {
        $response = $this->post(route('admin.promotions.store'), [
            'name' => 'Greedy Promo',
            'multiplier' => '10.5',
            'scope' => Promotion::SCOPE_GLOBAL,
            'starts_at' => now()->addDay()->format('Y-m-d\TH:i'),
            'ends_at' => now()->addDays(2)->format('Y-m-d\TH:i'),
            'enabled' => '1',
        ]);

        $response->assertSessionHasErrors(['multiplier']);
    }

    public function test_overlapping_promotion_warns_but_still_saves(): void
    {
        $this->makePromotion(['name' => 'Existing Fest', 'scope' => Promotion::SCOPE_GLOBAL]);

        $response = $this->post(route('admin.promotions.store'), [
            'name' => 'Overlapping Fest',
            'multiplier' => '2',
            'scope' => Promotion::SCOPE_GLOBAL,
            'starts_at' => now()->subHour()->format('Y-m-d\TH:i'),
            'ends_at' => now()->addHour()->format('Y-m-d\TH:i'),
            'enabled' => '1',
        ]);

        $response->assertRedirect(route('admin.promotions.index'));
        $response->assertSessionHas('warning');

        // Warned, not blocked: the promotion exists.
        $this->assertNotNull(Promotion::where('name', 'Overlapping Fest')->first());
    }

    public function test_non_overlapping_promotion_has_no_warning(): void
    {
        $this->makePromotion(['name' => 'Existing Fest', 'scope' => Promotion::SCOPE_GLOBAL]);

        $response = $this->post(route('admin.promotions.store'), [
            'name' => 'Later Fest',
            'multiplier' => '2',
            'scope' => Promotion::SCOPE_GLOBAL,
            'starts_at' => now()->addDays(10)->format('Y-m-d\TH:i'),
            'ends_at' => now()->addDays(12)->format('Y-m-d\TH:i'),
            'enabled' => '1',
        ]);

        $response->assertRedirect(route('admin.promotions.index'));
        $response->assertSessionMissing('warning');
    }

    public function test_seeded_diwali_demo_is_expired_and_inert(): void
    {
        $this->seed(\Database\Seeders\PromotionSeeder::class);

        $promo = Promotion::where('slug', 'diwali-dhamaka-demo')->firstOrFail();

        $this->assertFalse($promo->enabled);
        $this->assertSame('disabled', $promo->status());
        $this->assertFalse($promo->isLive());
        $this->assertStringContainsString('(demo)', $promo->name);
    }
}
