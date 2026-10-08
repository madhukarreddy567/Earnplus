<?php

namespace Tests\Feature\Offerwall;

use App\Models\OfferwallProvider;
use Database\Seeders\OfferwallSeeder;
use Database\Seeders\SettingSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Real provider definitions carry everything an admin needs to go
 * live after approval: verified postback field mapping, signature
 * scheme, docs — and stay disabled until real credentials are pasted
 * in. Specs were verified against each network's official
 * documentation on 2026-10-06 (see docs/OFFERWALL_INTEGRATIONS.md).
 */
class ProviderDefinitionsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(SettingSeeder::class);
        $this->seed(OfferwallSeeder::class);
    }

    public function test_adgate_matches_verified_spec_and_stays_disabled(): void
    {
        $provider = OfferwallProvider::where('slug', 'adgate')->firstOrFail();

        $this->assertFalse($provider->enabled);
        $this->assertNotEmpty($provider->postback_secret);

        $config = $provider->config;
        $this->assertSame('verified', $config['verification_status']);
        $this->assertSame('GET', $config['postback_method']);
        $this->assertStringContainsString('/postback/adgate', $config['postback_url']);

        // Verified param names from AdGate's official postback example.
        $map = $config['param_map'];
        $this->assertSame('provider_tx_id', $map['tx_id']);
        $this->assertSame('user_id', $map['user_id']);

        // AdGate documents no signature -> engine must skip HMAC for it.
        $this->assertSame('none', $config['signature']);

        // Chargebacks (status=0) are flagged, never credited.
        $this->assertSame('status', $config['chargeback_param']);
        $this->assertContains('0', $config['chargeback_values']);

        // Wall format comes from the official README.
        $this->assertStringContainsString(
            'wall.adgaterewards.com',
            $config['offer_url_template_example']
        );
    }

    public function test_adgem_matches_verified_spec_and_stays_disabled(): void
    {
        $provider = OfferwallProvider::where('slug', 'adgem')->firstOrFail();

        $this->assertFalse($provider->enabled);
        $this->assertNotEmpty($provider->postback_secret);

        $config = $provider->config;
        $this->assertSame('verified', $config['verification_status']);
        $this->assertSame('GET', $config['postback_method']);
        $this->assertStringContainsString('/postback/adgem', $config['postback_url']);

        // Verified macro names from AdGem's publisher docs.
        $map = $config['param_map'];
        $this->assertSame('user_id', $map['player_id']);
        $this->assertSame('provider_tx_id', $map['transaction_id']);
        $this->assertSame('payout', $map['payout']);

        // AdGem v2 server-postback hashing.
        $this->assertSame('adgem_v2', $config['signature']);

        $this->assertStringContainsString(
            'api.adgem.com/v1/wall',
            $config['offer_url_template_example']
        );
    }

    public function test_timewall_matches_verified_spec_and_stays_disabled(): void
    {
        $provider = OfferwallProvider::where('slug', 'timewall')->firstOrFail();

        $this->assertFalse($provider->enabled);
        $this->assertNotEmpty($provider->postback_secret);

        $config = $provider->config;

        // Verified 2026-10-07 from the owner's TimeWall site-owner
        // dashboard (see docs/OFFERWALL_INTEGRATIONS.md).
        $this->assertSame('verified', $config['verification_status']);
        $this->assertSame('GET', $config['postback_method']);
        $this->assertStringContainsString('/postback/timewall', $config['postback_url']);
        $this->assertStringContainsString('{userID}', $config['postback_url_template']);

        $map = $config['param_map'];
        $this->assertSame('user_id', $map['userid']);
        $this->assertSame('provider_tx_id', $map['txid']);
        $this->assertSame('revenue_usd', $map['revenue']);
        $this->assertSame('coins', $map['currency']);
        $this->assertSame('signature', $map['hash']);

        // TimeWall's {hash} = SHA256(userID . revenue . SecretKey).
        $this->assertSame('timewall_sha256', $config['signature']);

        // Owner's 2026-10-07 decision: 5000 coins per $1 USD (≈55/45
        // user/owner split at 100 Coins = ₹1).
        $this->assertSame(5000, $config['timewall_currency_rate']);

        // Their postback server IPs are seeded into the whitelist.
        $this->assertContains('18.156.132.55', $provider->ipWhitelist());
        $this->assertContains('51.81.120.73', $provider->ipWhitelist());
        $this->assertContains('142.111.248.18', $provider->ipWhitelist());
    }

    public function test_all_real_providers_still_require_approval(): void
    {
        $live = OfferwallProvider::where('enabled', true)->pluck('slug')->all();

        // Only the sandbox may be live out of the box.
        $this->assertSame(['demo'], $live);
    }
}
