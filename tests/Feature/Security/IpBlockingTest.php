<?php

namespace Tests\Feature\Security;

use App\Models\BlockedIp;
use App\Models\SecurityEvent;
use App\Models\Setting;
use Database\Seeders\SettingSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class IpBlockingTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(SettingSeeder::class);
    }

    public function test_blocked_ip_gets_neutral_403_on_web_routes(): void
    {
        BlockedIp::create(['ip_or_cidr' => '9.9.9.9', 'reason' => 'test']);

        $response = $this->get('/', ['REMOTE_ADDR' => '9.9.9.9']);

        $response->assertForbidden();
        $response->assertSee('Access denied');
        // No information leak about why.
        $response->assertDontSee('blocklist');
        $response->assertDontSee('9.9.9.9');
    }

    public function test_blocked_ip_gets_json_403_for_json_requests(): void
    {
        BlockedIp::create(['ip_or_cidr' => '9.9.9.9']);

        $response = $this->getJson('/', ['REMOTE_ADDR' => '9.9.9.9']);

        $response->assertForbidden();
        $response->assertJson(['message' => 'Forbidden.']);
    }

    public function test_cidr_range_blocks_members(): void
    {
        BlockedIp::create(['ip_or_cidr' => '10.20.0.0/16']);

        $this->get('/', ['REMOTE_ADDR' => '10.20.5.6'])->assertForbidden();
        $this->get('/', ['REMOTE_ADDR' => '10.21.0.1'])->assertOk();
    }

    public function test_expired_block_is_ignored(): void
    {
        BlockedIp::create([
            'ip_or_cidr' => '9.9.9.9',
            'expires_at' => now()->subHour(),
        ]);

        $this->get('/', ['REMOTE_ADDR' => '9.9.9.9'])->assertOk();
    }

    public function test_whitelisted_ip_skips_blocklist(): void
    {
        BlockedIp::create(['ip_or_cidr' => '0.0.0.0/0']);
        Setting::set('security_ip_whitelist', '9.9.9.9', 'security');

        $this->get('/', ['REMOTE_ADDR' => '9.9.9.9'])->assertOk();
    }

    public function test_block_hit_is_logged_as_security_event(): void
    {
        BlockedIp::create(['ip_or_cidr' => '9.9.9.9']);

        $this->get('/', ['REMOTE_ADDR' => '9.9.9.9']);

        $this->assertDatabaseHas('security_events', [
            'type' => SecurityEvent::TYPE_IP_BLOCKED,
            'ip' => '9.9.9.9',
        ]);
    }

    public function test_unblocked_ip_passes(): void
    {
        $this->get('/', ['REMOTE_ADDR' => '1.2.3.4'])->assertOk();
    }
}
