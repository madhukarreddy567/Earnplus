<?php

namespace Tests\Feature\Security;

use App\Models\Admin;
use App\Models\BlockedIp;
use App\Models\SecurityEvent;
use App\Models\Setting;
use Database\Seeders\AdminSeeder;
use Database\Seeders\SettingSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class AdminSecurityCenterTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(SettingSeeder::class);
        $this->seed(AdminSeeder::class);
    }

    protected function loginAsAdmin(): Admin
    {
        $admin = Admin::query()->first();
        $this->actingAs($admin, 'admin');

        return $admin;
    }

    public function test_guests_cannot_open_security_center(): void
    {
        $this->get(route('admin.security.index'))->assertRedirect(route('admin.login'));
    }

    public function test_admin_can_view_security_center(): void
    {
        $this->loginAsAdmin();

        $this->get(route('admin.security.index'))
            ->assertOk()
            ->assertSee('Security center')
            ->assertSee('IP blocklist')
            ->assertSee('Emergency lockdown');
    }

    public function test_admin_can_block_and_unblock_ip(): void
    {
        $this->loginAsAdmin();

        $this->post(route('admin.security.blocks.store'), [
            'ip_or_cidr' => '9.9.9.9',
            'reason' => 'test block',
        ])->assertRedirect();

        $this->assertDatabaseHas('blocked_ips', ['ip_or_cidr' => '9.9.9.9']);

        // The block is enforced immediately.
        $this->get('/', ['REMOTE_ADDR' => '9.9.9.9'])->assertForbidden();

        $block = BlockedIp::where('ip_or_cidr', '9.9.9.9')->first();
        $this->delete(route('admin.security.blocks.destroy', $block))->assertRedirect();
        $this->assertDatabaseMissing('blocked_ips', ['ip_or_cidr' => '9.9.9.9']);
    }

    public function test_invalid_cidr_is_rejected(): void
    {
        $this->loginAsAdmin();

        $this->post(route('admin.security.blocks.store'), ['ip_or_cidr' => 'not-an-ip'])
            ->assertSessionHasErrors('ip_or_cidr');

        $this->assertDatabaseCount('blocked_ips', 0);
    }

    public function test_admin_cannot_block_their_own_ip(): void
    {
        $this->loginAsAdmin();

        // Test requests come from 127.0.0.1.
        $this->post(route('admin.security.blocks.store'), ['ip_or_cidr' => '127.0.0.0/8'])
            ->assertSessionHasErrors('ip_or_cidr');

        $this->assertDatabaseCount('blocked_ips', 0);
    }

    public function test_security_settings_save_with_cidr_validation(): void
    {
        $this->loginAsAdmin();

        $this->post(route('admin.security.settings'), [
            'trust_proxy_headers' => '1',
            'trusted_proxy_cidrs' => "10.0.0.0/8\nbogus",
            'security_ip_whitelist' => '',
        ])->assertSessionHasErrors('trusted_proxy_cidrs');

        $this->post(route('admin.security.settings'), [
            'trust_proxy_headers' => '1',
            'trusted_proxy_cidrs' => "10.0.0.0/8",
            'security_ip_whitelist' => "203.0.113.7",
            'hsts_enabled' => '1',
        ])->assertRedirect();

        $this->assertTrue(setting_bool('trust_proxy_headers'));
        $this->assertSame('10.0.0.0/8', setting('trusted_proxy_cidrs'));
        $this->assertSame('203.0.113.7', setting('security_ip_whitelist'));
        $this->assertTrue(setting_bool('hsts_enabled'));
    }

    public function test_emergency_lockdown_enables_maintenance_and_kills_sessions(): void
    {
        $this->loginAsAdmin();

        // A session row exists for the current login (database driver).
        DB::table('sessions')->insert([
            'id' => 'test-session-id',
            'user_id' => null,
            'ip_address' => '127.0.0.1',
            'user_agent' => 'test',
            'payload' => 'x',
            'last_activity' => time(),
        ]);

        $response = $this->post(route('admin.security.lockdown'));

        $response->assertRedirect(route('admin.login'));
        $this->assertTrue(setting_bool('maintenance_mode'));

        // All sessions destroyed, including the admin's.
        $this->assertSame(0, DB::table('sessions')->count());
        $this->assertGuest('admin');

        // Lockdown is logged.
        $this->assertDatabaseHas('security_events', [
            'type' => SecurityEvent::TYPE_LOCKDOWN,
        ]);

        // Maintenance mode is what visitors will hit (its 503 rendering
        // is covered by the Phase 9 maintenance tests).
        $this->assertTrue(setting_bool('maintenance_mode'));
    }
}
