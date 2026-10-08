<?php

namespace Tests\Feature\Offerwall;

use App\Models\OfferwallClick;
use App\Models\OfferwallConversion;
use App\Models\OfferwallProvider;
use App\Models\Wallet;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DemoEndToEndTest extends TestCase
{
    use RefreshDatabase;
    use CreatesOfferwalls;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpCreatesOfferwalls();
    }

    public function test_tasks_page_renders_with_demo_tasks(): void
    {
        $user = $this->makeUser();
        $this->actingAs($user);

        $response = $this->get(route('tasks'));

        $response->assertOk();
        $response->assertSee('Earn tasks');
        $response->assertSee('Install the Demo Quest app');
        $response->assertSee('Complete a 5-minute survey');
    }

    public function test_tasks_page_requires_login(): void
    {
        $this->get(route('tasks'))->assertRedirect('/login');
    }

    public function test_demo_task_completion_credits_wallet_through_real_postback(): void
    {
        $user = $this->makeUser();
        $this->actingAs($user);

        $response = $this->post(route('tasks.demo.complete', 'survey'));

        $response->assertRedirect(route('tasks'));
        $response->assertSessionHas('success');

        // 200 payout × 100% demo share = 200 coins.
        $this->assertSame(200, Wallet::where('user_id', $user->id)->first()->coins);

        $conversion = OfferwallConversion::first();
        $this->assertNotNull($conversion);
        $this->assertSame(OfferwallConversion::STATUS_CREDITED, $conversion->status);
        $this->assertSame(200, $conversion->user_coins);
        $this->assertNotNull($conversion->raw_payload);

        $click = OfferwallClick::first();
        $this->assertSame(OfferwallClick::STATUS_CONVERTED, $click->status);
        $this->assertSame($click->click_uid, $conversion->click_uid);
    }

    public function test_each_demo_completion_is_a_distinct_conversion(): void
    {
        $user = $this->makeUser();
        $this->actingAs($user);

        $this->post(route('tasks.demo.complete', 'video'));
        $this->post(route('tasks.demo.complete', 'video'));

        // 30 coins × 2 completions.
        $this->assertSame(60, Wallet::where('user_id', $user->id)->first()->coins);
        $this->assertSame(2, OfferwallConversion::count());
    }

    public function test_unknown_demo_task_returns_404(): void
    {
        $user = $this->makeUser();
        $this->actingAs($user);

        $this->post(route('tasks.demo.complete', 'no-such-task'))->assertNotFound();
    }

    public function test_seeded_providers_are_present_but_only_demo_is_enabled(): void
    {
        $this->assertSame(9, OfferwallProvider::count());

        $enabled = OfferwallProvider::where('enabled', true)->pluck('slug')->all();
        $this->assertSame(['demo'], $enabled);

        foreach (['wannads', 'bitlabs', 'adgate', 'cpx-research', 'offertoro', 'timewall', 'adgem', 'rewards-offerwall'] as $slug) {
            $this->assertFalse(
                OfferwallProvider::where('slug', $slug)->first()->enabled,
                "{$slug} should be disabled until real credentials are added"
            );
        }
    }
}
