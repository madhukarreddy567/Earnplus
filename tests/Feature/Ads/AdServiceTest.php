<?php

namespace Tests\Feature\Ads;

use App\Models\AdImpression;
use App\Models\AdPlacement;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdServiceTest extends TestCase
{
    use RefreshDatabase;
    use CreatesAds;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpCreatesAds();
    }

    public function test_disabled_placement_is_not_eligible(): void
    {
        $placement = $this->makePlacement(['enabled' => false]);

        $eligible = $this->ads->eligiblePlacements(
            'dashboard-banner', 'dashboard', 'desktop', $this->adRequest()
        );

        $this->assertTrue($eligible->where('id', $placement->id)->isEmpty());
    }

    public function test_placement_on_disabled_network_is_not_eligible(): void
    {
        $network = $this->makeNetwork(['enabled' => false]);
        $placement = $this->makePlacement(['network' => $network]);

        $eligible = $this->ads->eligiblePlacements(
            'dashboard-banner', 'dashboard', 'desktop', $this->adRequest()
        );

        $this->assertTrue($eligible->where('id', $placement->id)->isEmpty());
    }

    public function test_device_targeting(): void
    {
        $mobile = $this->makePlacement(['device' => AdPlacement::DEVICE_MOBILE]);

        $desktopEligible = $this->ads->eligiblePlacements(
            'dashboard-banner', 'dashboard', 'desktop', $this->adRequest('Mozilla/5.0 (Windows NT 10.0)')
        );
        $this->assertTrue($desktopEligible->where('id', $mobile->id)->isEmpty());

        $mobileEligible = $this->ads->eligiblePlacements(
            'dashboard-banner', 'dashboard', 'mobile',
            $this->adRequest('Mozilla/5.0 (iPhone; CPU iPhone OS 17_0 like Mac OS X) Mobile/15E148')
        );
        $this->assertTrue($mobileEligible->where('id', $mobile->id)->isNotEmpty());
    }

    public function test_page_targeting(): void
    {
        $placement = $this->makePlacement(['pages' => ['dashboard']]);

        $request = $this->adRequest();
        $this->assertTrue(
            $this->ads->eligiblePlacements('dashboard-banner', 'tasks', 'desktop', $request)
                ->where('id', $placement->id)->isEmpty()
        );
        $this->assertTrue(
            $this->ads->eligiblePlacements('dashboard-banner', 'dashboard', 'desktop', $request)
                ->where('id', $placement->id)->isNotEmpty()
        );
    }

    public function test_frequency_cap_per_session(): void
    {
        $placement = $this->makePlacement(['frequency_cap_per_session' => 2]);
        $request = $this->adRequest();

        $this->assertStringContainsString('TEST AD', $this->ads->renderSlot('dashboard-banner', 'dashboard', null, $request));
        $this->assertStringContainsString('TEST AD', $this->ads->renderSlot('dashboard-banner', 'dashboard', null, $request));
        // Cap reached — renders nothing.
        $this->assertSame('', $this->ads->renderSlot('dashboard-banner', 'dashboard', null, $request));
    }

    public function test_weighted_rotation_boundaries(): void
    {
        $a = $this->makePlacement(['priority' => 10]);
        $b = $this->makePlacement(['priority' => 30]);
        $candidates = collect([$a, $b]);

        // Total weight 40: roll < 0.25 → A, roll >= 0.25 → B.
        $this->assertSame($a->id, $this->ads->selectPlacement($candidates, 0.0)->id);
        $this->assertSame($a->id, $this->ads->selectPlacement($candidates, 0.249)->id);
        $this->assertSame($b->id, $this->ads->selectPlacement($candidates, 0.25)->id);
        $this->assertSame($b->id, $this->ads->selectPlacement($candidates, 0.99)->id);
    }

    public function test_rotation_distributes_across_placements(): void
    {
        $a = $this->makePlacement(['priority' => 10]);
        $b = $this->makePlacement(['priority' => 10]);
        $request = $this->adRequest();

        // High cap so the session cap never interferes.
        $a->update(['frequency_cap_per_session' => 100]);
        $b->update(['frequency_cap_per_session' => 100]);

        for ($i = 0; $i < 30; $i++) {
            $this->ads->renderSlot('dashboard-banner', 'dashboard', null, $request);
        }

        $counts = [
            AdImpression::where('placement_id', $a->id)->count(),
            AdImpression::where('placement_id', $b->id)->count(),
        ];

        $this->assertSame(30, array_sum($counts));
        $this->assertGreaterThan(0, $counts[0], 'Placement A never served — rotation is stuck.');
        $this->assertGreaterThan(0, $counts[1], 'Placement B never served — rotation is stuck.');
    }

    public function test_impression_is_logged(): void
    {
        $user = $this->makeUser();
        $placement = $this->makePlacement();
        $request = $this->adRequest();

        $this->ads->renderSlot('dashboard-banner', 'dashboard', $user, $request);

        $impression = AdImpression::where('placement_id', $placement->id)->first();
        $this->assertNotNull($impression);
        $this->assertSame($user->id, $impression->user_id);
        $this->assertSame('127.0.0.1', $impression->ip);
        $this->assertSame('desktop', $impression->device);
        $this->assertSame('dashboard', $impression->page);
        $this->assertFalse($impression->rewarded);
    }

    public function test_custom_code_is_rendered_as_is(): void
    {
        $this->makePlacement(['custom_code' => '<script src="https://ads.example.com/x.js"></script>']);

        $html = $this->ads->renderSlot('dashboard-banner', 'dashboard', null, $this->adRequest());

        // Admin-pasted code runs as-is by design (trust model documented in README).
        $this->assertStringContainsString('<script src="https://ads.example.com/x.js"></script>', $html);
        $this->assertStringNotContainsString('&lt;script', $html);
    }

    public function test_unknown_slot_renders_nothing(): void
    {
        $html = $this->ads->renderSlot('no-such-slot', 'dashboard', null, $this->adRequest());

        $this->assertSame('', $html);
    }

    public function test_helper_render_ad_returns_empty_for_ineligible_slot(): void
    {
        $this->assertSame('', render_ad('no-such-slot', 'dashboard'));
    }
}
