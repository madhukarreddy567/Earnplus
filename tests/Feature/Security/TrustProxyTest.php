<?php

namespace Tests\Feature\Security;

use App\Models\BlockedIp;
use App\Models\Setting;
use Database\Seeders\SettingSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TrustProxyTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(SettingSeeder::class);
    }

    public function test_forwarded_headers_are_ignored_by_default(): void
    {
        Setting::set('email_auth_enabled', '1', 'features');

        // Spoof attempt: an untrusted client must not be able to fake its IP.
        $this->post('/login', [
            'email' => 'nobody@example.com',
            'password' => 'wrong',
        ], [
            'REMOTE_ADDR' => '127.0.0.1',
            'HTTP_X_FORWARDED_FOR' => '9.9.9.9',
        ]);

        $this->assertDatabaseHas('login_logs', [
            'email' => 'nobody@example.com',
            'ip' => '127.0.0.1',
        ]);
    }

    public function test_trusted_proxy_resolves_real_client_ip(): void
    {
        Setting::set('trust_proxy_headers', '1', 'security');
        Setting::set('trusted_proxy_cidrs', '127.0.0.1/32', 'security');
        Setting::set('email_auth_enabled', '1', 'features');

        // A failed login is logged with the request IP — behind the trusted
        // proxy it must be the forwarded client IP, not the proxy.
        $this->post('/login', [
            'email' => 'nobody@example.com',
            'password' => 'wrong',
        ], [
            'REMOTE_ADDR' => '127.0.0.1',
            'HTTP_X_FORWARDED_FOR' => '9.9.9.9',
        ]);

        $this->assertDatabaseHas('login_logs', [
            'email' => 'nobody@example.com',
            'ip' => '9.9.9.9',
        ]);
    }

    public function test_blocklist_applies_to_forwarded_ip_behind_trusted_proxy(): void
    {
        Setting::set('trust_proxy_headers', '1', 'security');
        Setting::set('trusted_proxy_cidrs', '127.0.0.1/32', 'security');
        BlockedIp::create(['ip_or_cidr' => '9.9.9.9']);

        $this->get('/', [
            'REMOTE_ADDR' => '127.0.0.1',
            'HTTP_X_FORWARDED_FOR' => '9.9.9.9',
        ])->assertForbidden();
    }

    public function test_peer_outside_configured_ranges_is_not_trusted(): void
    {
        Setting::set('trust_proxy_headers', '1', 'security');
        Setting::set('trusted_proxy_cidrs', '10.0.0.0/8', 'security');
        Setting::set('email_auth_enabled', '1', 'features');

        $this->post('/login', [
            'email' => 'nobody@example.com',
            'password' => 'wrong',
        ], [
            'REMOTE_ADDR' => '127.0.0.1',
            'HTTP_X_FORWARDED_FOR' => '9.9.9.9',
        ]);

        $this->assertDatabaseHas('login_logs', [
            'email' => 'nobody@example.com',
            'ip' => '127.0.0.1',
        ]);
    }
}
