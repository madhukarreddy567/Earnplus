<?php

namespace Tests\Feature\License;

use App\Models\Admin;
use App\Models\LicenseViolation;
use App\Models\Setting;
use App\Services\LicenseService;
use Database\Seeders\AdminSeeder;
use Database\Seeders\SettingSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Phase 13: admin license page — status, settings, violations, kill
 * switch (super_admin only, double confirmation), secret regeneration.
 */
class LicenseAdminTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(SettingSeeder::class);
        $this->seed(AdminSeeder::class);
    }

    protected function loginAsSuperAdmin(): Admin
    {
        $admin = Admin::query()->first();
        $this->actingAs($admin, 'admin');

        return $admin;
    }

    protected function loginAsPlainAdmin(): Admin
    {
        $admin = Admin::create([
            'name' => 'Staff',
            'email' => 'staff@example.com',
            'password' => bcrypt('password'),
            'role' => 'admin',
            'is_active' => true,
        ]);
        $this->actingAs($admin, 'admin');

        return $admin;
    }

    public function test_guests_cannot_open_license_page(): void
    {
        $this->get(route('admin.license.index'))->assertRedirect(route('admin.login'));
    }

    public function test_admin_can_view_license_page(): void
    {
        $this->loginAsSuperAdmin();

        $this->get(route('admin.license.index'))
            ->assertOk()
            ->assertSee('License & domain lock')
            ->assertSee('License status')
            ->assertSee('Violation log')
            ->assertSee('Emergency kill switch');
    }

    public function test_admin_can_save_settings(): void
    {
        $this->loginAsSuperAdmin();

        // 'localhost' matches the test request host, so the production
        // safety check (which refuses domains that would lock the owner
        // out) lets it through.
        $this->post(route('admin.license.settings'), [
            'app_mode' => 'production',
            'licensed_domain' => 'localhost',
            'license_domain_aliases' => 'staging.localhost',
            'license_violation_block_threshold' => '5',
        ])->assertRedirect(route('admin.license.index'));

        $this->assertSame('production', Setting::get('app_mode'));
        $this->assertSame('localhost', Setting::get('licensed_domain'));
        $this->assertSame('staging.localhost', Setting::get('license_domain_aliases'));
        $this->assertSame('5', Setting::get('license_violation_block_threshold'));
    }

    public function test_invalid_domain_rejected(): void
    {
        $this->loginAsSuperAdmin();

        $this->post(route('admin.license.settings'), [
            'app_mode' => 'development',
            'licensed_domain' => 'not a domain!!!',
            'license_violation_block_threshold' => '10',
        ])->assertSessionHasErrors('licensed_domain');
    }

    public function test_production_domain_must_match_current_host(): void
    {
        $this->loginAsSuperAdmin();

        // The test host is localhost — licensing example.com in
        // production would lock the owner out, so it must be refused.
        $this->post(route('admin.license.settings'), [
            'app_mode' => 'production',
            'licensed_domain' => 'example.com',
            'license_violation_block_threshold' => '10',
        ])->assertSessionHasErrors('licensed_domain');

        $this->assertNotSame('production', Setting::get('app_mode'));
    }

    public function test_kill_switch_requires_super_admin(): void
    {
        $this->loginAsPlainAdmin();

        $this->post(route('admin.license.kill'), ['acknowledge' => '1'])->assertForbidden();
        $this->post(route('admin.license.unlock'))->assertForbidden();

        $this->assertFalse(app(LicenseService::class)->isLocked());
    }

    public function test_kill_switch_requires_acknowledgement(): void
    {
        $this->loginAsSuperAdmin();

        $this->post(route('admin.license.kill'), [])->assertSessionHasErrors('acknowledge');
        $this->assertFalse(app(LicenseService::class)->isLocked());
    }

    public function test_super_admin_can_kill_and_unlock(): void
    {
        $this->loginAsSuperAdmin();

        $this->post(route('admin.license.kill'), ['acknowledge' => '1'])
            ->assertRedirect(route('admin.license.index'));

        $this->assertTrue(app(LicenseService::class)->isLocked());
        $this->assertDatabaseHas('license_violations', ['type' => LicenseViolation::TYPE_KILL_SWITCH]);

        // Locked state is visible on the page.
        $this->get(route('admin.license.index'))->assertSee('Kill switch is ACTIVE');

        $this->post(route('admin.license.unlock'))
            ->assertRedirect(route('admin.license.index'));

        $this->assertFalse(app(LicenseService::class)->isLocked());
    }

    public function test_kill_secret_regeneration(): void
    {
        $this->loginAsSuperAdmin();

        $old = app(LicenseService::class)->killSecret();
        $this->assertNotSame('', $old);

        $this->post(route('admin.license.kill-secret'))->assertRedirect();

        $new = app(LicenseService::class)->killSecret();
        $this->assertNotSame($old, $new);
    }

    public function test_violations_list_with_filters(): void
    {
        $this->loginAsSuperAdmin();

        LicenseViolation::create(['type' => LicenseViolation::TYPE_DOMAIN_MISMATCH, 'ip' => '1.2.3.4', 'host' => 'evil.com']);
        LicenseViolation::create(['type' => LicenseViolation::TYPE_SIGNATURE_TAMPER, 'ip' => '5.6.7.8', 'host' => 'example.com']);

        $this->get(route('admin.license.index'))
            ->assertOk()
            ->assertSee('evil.com')
            ->assertSee('1.2.3.4');

        $this->get(route('admin.license.index', ['type' => LicenseViolation::TYPE_SIGNATURE_TAMPER]))
            ->assertOk()
            ->assertSee('5.6.7.8')
            ->assertDontSee('evil.com');

        $this->get(route('admin.license.index', ['q' => '1.2.3.4']))
            ->assertOk()
            ->assertSee('evil.com')
            ->assertDontSee('5.6.7.8');
    }

    public function test_license_seeder_is_idempotent(): void
    {
        Setting::set('licensed_domain', 'myshop.com', 'license');

        $this->seed(\Database\Seeders\LicenseSeeder::class);

        // Existing admin choices are never overwritten…
        $this->assertSame('myshop.com', Setting::get('licensed_domain'));
        // …but missing defaults are filled in.
        $this->assertSame(LicenseService::SIGNATURE, Setting::get('license_signature'));
        $this->assertSame('development', Setting::get('app_mode'));
    }
}
