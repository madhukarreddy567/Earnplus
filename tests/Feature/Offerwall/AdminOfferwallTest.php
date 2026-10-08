<?php

namespace Tests\Feature\Offerwall;

use App\Models\CoinTransaction;
use App\Models\OfferwallConversion;
use App\Models\OfferwallProvider;
use App\Models\Wallet;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminOfferwallTest extends TestCase
{
    use RefreshDatabase;
    use CreatesOfferwalls;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpCreatesOfferwalls();
        $this->loginAsAdmin();
    }

    public function test_guests_cannot_open_offerwall_admin(): void
    {
        auth('admin')->logout();

        $this->get(route('admin.offerwalls.index'))->assertRedirect(route('admin.login'));
    }

    public function test_admin_sees_provider_list_with_stats(): void
    {
        $response = $this->get(route('admin.offerwalls.index'));

        $response->assertOk();
        $response->assertSee('Demo Tasks');
        $response->assertSee('Wannads');
    }

    public function test_admin_can_create_a_provider(): void
    {
        $response = $this->post(route('admin.offerwalls.store'), [
            'name' => 'New Wall',
            'slug' => 'new-wall',
            'user_revenue_share' => 60,
            'enabled' => '1',
            'tagline' => 'Fresh offers',
            'offer_url_template' => 'https://example.com/?uid={click_uid}',
        ]);

        $response->assertRedirect();
        $provider = OfferwallProvider::where('slug', 'new-wall')->first();
        $this->assertNotNull($provider);
        $this->assertNotEmpty($provider->postback_secret);
        $this->assertSame(60.0, $provider->user_revenue_share);
        $this->assertTrue($provider->enabled);
        $this->assertSame('https://example.com/?uid={click_uid}', $provider->config['offer_url_template']);
    }

    public function test_admin_can_update_a_provider(): void
    {
        $provider = $this->makeProvider();

        $this->put(route('admin.offerwalls.update', $provider), [
            'name' => 'Renamed',
            'slug' => 'test-provider',
            'user_revenue_share' => 80,
        ])->assertRedirect(route('admin.offerwalls.index'));

        $this->assertSame('Renamed', $provider->fresh()->name);
        $this->assertSame(80.0, $provider->fresh()->user_revenue_share);
    }

    public function test_admin_can_regenerate_the_postback_secret(): void
    {
        $provider = $this->makeProvider();
        $old = $provider->postback_secret;

        $this->post(route('admin.offerwalls.regenerate-secret', $provider))->assertRedirect();

        $this->assertNotSame($old, $provider->fresh()->postback_secret);
    }

    public function test_admin_cannot_delete_the_demo_sandbox(): void
    {
        $demo = OfferwallProvider::where('slug', 'demo')->first();

        $this->delete(route('admin.offerwalls.destroy', $demo))->assertRedirect();

        $this->assertNotNull($demo->fresh());
    }

    public function test_admin_can_manually_credit_a_pending_conversion(): void
    {
        $provider = $this->makeProvider();
        $user = $this->makeUser();

        $conversion = OfferwallConversion::create([
            'provider_id' => $provider->id,
            'user_id' => $user->id,
            'provider_tx_id' => 'tx-manual-1',
            'payout_coins' => 100,
            'user_coins' => 70,
            'status' => OfferwallConversion::STATUS_PENDING,
            'raw_payload' => ['note' => 'held for review'],
        ]);

        $this->post(route('admin.offerwalls.conversions.credit', $conversion))
            ->assertRedirect();

        $this->assertSame(
            OfferwallConversion::STATUS_CREDITED,
            $conversion->fresh()->status
        );
        $this->assertSame(70, Wallet::where('user_id', $user->id)->first()->coins);
    }

    public function test_manual_credit_is_idempotent(): void
    {
        $provider = $this->makeProvider();
        $user = $this->makeUser();

        $conversion = OfferwallConversion::create([
            'provider_id' => $provider->id,
            'user_id' => $user->id,
            'provider_tx_id' => 'tx-manual-2',
            'payout_coins' => 100,
            'user_coins' => 70,
            'status' => OfferwallConversion::STATUS_PENDING,
            'raw_payload' => [],
        ]);

        $this->post(route('admin.offerwalls.conversions.credit', $conversion));
        $this->post(route('admin.offerwalls.conversions.credit', $conversion));

        $this->assertSame(70, Wallet::where('user_id', $user->id)->first()->coins);
        $this->assertSame(
            1,
            CoinTransaction::where('idempotency_key', 'offerwall:test-provider:tx-manual-2')->count()
        );
    }

    public function test_admin_can_reject_a_pending_conversion(): void
    {
        $provider = $this->makeProvider();
        $user = $this->makeUser();

        $conversion = OfferwallConversion::create([
            'provider_id' => $provider->id,
            'user_id' => $user->id,
            'provider_tx_id' => 'tx-manual-3',
            'payout_coins' => 100,
            'user_coins' => 70,
            'status' => OfferwallConversion::STATUS_PENDING,
            'raw_payload' => [],
        ]);

        $this->post(route('admin.offerwalls.conversions.reject', $conversion), [
            'reason' => 'Suspicious activity',
        ])->assertRedirect();

        $fresh = $conversion->fresh();
        $this->assertSame(OfferwallConversion::STATUS_REJECTED, $fresh->status);
        $this->assertSame('Suspicious activity', $fresh->meta['reject_reason']);
        $this->assertNull(Wallet::where('user_id', $user->id)->first());
    }

    public function test_conversion_log_filters_by_status(): void
    {
        $provider = $this->makeProvider();
        $user = $this->makeUser();

        OfferwallConversion::create([
            'provider_id' => $provider->id,
            'user_id' => $user->id,
            'provider_tx_id' => 'tx-filter-1',
            'payout_coins' => 100,
            'user_coins' => 70,
            'status' => OfferwallConversion::STATUS_PENDING,
            'raw_payload' => [],
        ]);
        OfferwallConversion::create([
            'provider_id' => $provider->id,
            'user_id' => $user->id,
            'provider_tx_id' => 'tx-filter-2',
            'payout_coins' => 100,
            'user_coins' => 70,
            'status' => OfferwallConversion::STATUS_CREDITED,
            'raw_payload' => [],
        ]);

        $response = $this->get(route('admin.offerwalls.conversions', ['status' => 'pending']));

        $response->assertOk();
        $response->assertSee('tx-filter-1');
        $response->assertDontSee('tx-filter-2');
    }
}
