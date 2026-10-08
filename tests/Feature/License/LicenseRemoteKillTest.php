<?php

namespace Tests\Feature\License;

use App\Models\LicenseViolation;
use App\Models\Setting;
use App\Services\LicenseService;
use Database\Seeders\SettingSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Phase 13: remote kill URL — disabled by default (404), signed token
 * required, locks the app instantly when valid.
 */
class LicenseRemoteKillTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(SettingSeeder::class);
    }

    protected function license(): LicenseService
    {
        return app(LicenseService::class);
    }

    public function test_remote_kill_is_404_when_disabled(): void
    {
        $this->assertFalse($this->license()->remoteKillEnabled());

        $this->get(route('license.remote-kill', ['token' => $this->license()->remoteKillToken()]))
            ->assertNotFound();

        $this->assertFalse($this->license()->isLocked());
    }

    public function test_remote_kill_rejects_bad_token(): void
    {
        Setting::set('license_remote_kill_enabled', '1', 'license');

        $this->get(route('license.remote-kill', ['token' => 'wrong']))->assertNotFound();
        $this->get(route('license.remote-kill'))->assertNotFound();

        $this->assertFalse($this->license()->isLocked());
    }

    public function test_remote_kill_with_valid_token_locks_app(): void
    {
        Setting::set('license_remote_kill_enabled', '1', 'license');

        $response = $this->get(route('license.remote-kill', ['token' => $this->license()->remoteKillToken()]));

        $response->assertOk();
        $response->assertSee('OK', false);

        $this->assertTrue($this->license()->isLocked());
        $this->assertDatabaseHas('license_violations', [
            'type' => LicenseViolation::TYPE_KILL_SWITCH,
        ]);

        // The app is now locked for users.
        $this->get('http://example.com/')->assertStatus(503);
    }

    public function test_remote_kill_works_even_when_app_locked(): void
    {
        Setting::set('license_remote_kill_enabled', '1', 'license');
        $this->license()->activateKillSwitch('first');

        // Idempotent — still OK, still locked.
        $this->get(route('license.remote-kill', ['token' => $this->license()->remoteKillToken()]))
            ->assertOk();

        $this->assertTrue($this->license()->isLocked());
    }

    public function test_remote_kill_passes_through_license_middleware(): void
    {
        // Even in strict production on a wrong domain, the kill URL itself
        // must stay reachable (it is the owner's emergency handle).
        Setting::set('app_mode', 'production', 'license');
        Setting::set('licensed_domain', 'example.com', 'license');
        Setting::set('license_signature', LicenseService::SIGNATURE, 'license');
        Setting::set('license_remote_kill_enabled', '1', 'license');

        $this->get('http://other.com' . route('license.remote-kill', ['token' => $this->license()->remoteKillToken()], false))
            ->assertOk();

        $this->assertTrue($this->license()->isLocked());
    }
}
