<?php

namespace Tests\Feature\Ads;

use App\Models\AdReward;
use App\Models\Wallet;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Blade;
use Tests\TestCase;

class AdComponentTest extends TestCase
{
    use RefreshDatabase;
    use CreatesAds;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpCreatesAds();
    }

    public function test_component_renders_eligible_placement(): void
    {
        $this->makePlacement(['slot' => 'dashboard-banner']);

        $html = Blade::render('<x-ad placement="dashboard-banner" />', [], false);

        $this->assertStringContainsString('TEST AD', $html);
        $this->assertStringContainsString('data-ad-slot="dashboard-banner"', $html);
    }

    public function test_component_renders_nothing_when_ineligible(): void
    {
        // No placements seeded for this slot → empty string, no broken layout.
        $html = Blade::render('<x-ad placement="sidebar" />', [], false);

        $this->assertSame('', trim($html));
    }

    public function test_tasks_page_shows_reward_card_for_demo_placement(): void
    {
        $user = $this->makeUser();
        $this->actingAs($user);

        $response = $this->get(route('tasks'));

        $response->assertOk();
        $response->assertSee('Watch ad &amp; earn', false);
        $response->assertSee('data-reward-card', false);
        $response->assertSee(route('ads.reward', 'demo-rewarded'), false);
    }

    public function test_dashboard_renders_with_empty_banner_slot(): void
    {
        $user = $this->makeUser();
        $this->actingAs($user);

        // No placements for dashboard-banner → slot renders nothing, page fine.
        $response = $this->get(route('dashboard'));

        $response->assertOk();
        $response->assertSee('Total balance');
    }
}

class DemoRewardedEndToEndTest extends TestCase
{
    use RefreshDatabase;
    use CreatesAds;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpCreatesAds();
    }

    public function test_demo_rewarded_flow_credits_wallet_end_to_end(): void
    {
        $user = $this->makeUser();
        $this->actingAs($user);

        // Tasks page offers the rewarded card…
        $tasks = $this->get(route('tasks'));
        $tasks->assertOk();
        $tasks->assertSee('Watch ad &amp; earn', false);

        // …the user watches (countdown is cosmetic) and claims.
        $claim = $this->postJson(route('ads.reward', 'demo-rewarded'));
        $claim->assertOk();
        $claim->assertJson(['coins' => 5]);

        // Wallet + ledger + reward log all agree.
        $this->assertSame(5, Wallet::where('user_id', $user->id)->first()->coins);
        $this->assertSame(1, AdReward::where('user_id', $user->id)->count());
        $this->assertSame(
            5,
            (int) $claim->json('balance')
        );

        // Rewarded impression was logged too.
        $this->assertTrue(
            \App\Models\AdImpression::where('user_id', $user->id)
                ->where('rewarded', true)
                ->exists()
        );
    }
}
