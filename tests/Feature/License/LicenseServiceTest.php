<?php

namespace Tests\Feature\License;

use App\Models\Setting;
use App\Services\LicenseService;
use Database\Seeders\SettingSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Phase 13: LicenseService unit behavior — mode resolution, signature,
 * domain matching, kill switch state.
 */
class LicenseServiceTest extends TestCase
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

    public function test_default_mode_is_development(): void
    {
        $this->assertSame('development', $this->license()->mode());
        $this->assertTrue($this->license()->isDevelopment());
        $this->assertFalse($this->license()->isProduction());
    }

    public function test_mode_comes_from_db_setting(): void
    {
        Setting::set('app_mode', 'production', 'license');

        $this->assertSame('production', $this->license()->mode());
        $this->assertTrue($this->license()->isProduction());
    }

    public function test_unknown_mode_falls_back_to_development(): void
    {
        Setting::set('app_mode', 'staging', 'license');

        $this->assertSame('development', $this->license()->mode());
        $this->assertTrue($this->license()->isDevelopment());
    }

    public function test_signature_constant_matches_build(): void
    {
        $this->assertSame('HANDRIKAMADHUKARREDDY', LicenseService::SIGNATURE);
    }

    public function test_signature_valid_when_stored_matches(): void
    {
        Setting::set('license_signature', LicenseService::SIGNATURE, 'license');

        $this->assertTrue($this->license()->signatureValid());
    }

    public function test_signature_invalid_when_tampered_or_missing(): void
    {
        Setting::set('license_signature', 'TAMPERED', 'license');
        $this->assertFalse($this->license()->signatureValid());

        Setting::set('license_signature', '', 'license');
        $this->assertFalse($this->license()->signatureValid());
    }

    public function test_host_allowed_exact_and_www_variants(): void
    {
        Setting::set('licensed_domain', 'example.com', 'license');

        $this->assertTrue($this->license()->hostAllowed('example.com'));
        $this->assertTrue($this->license()->hostAllowed('www.example.com'));
        $this->assertFalse($this->license()->hostAllowed('evil.com'));
        $this->assertFalse($this->license()->hostAllowed('example.com.evil.com'));
    }

    public function test_host_allowed_when_domain_licensed_with_www(): void
    {
        Setting::set('licensed_domain', 'www.example.com', 'license');

        $this->assertTrue($this->license()->hostAllowed('example.com'));
        $this->assertTrue($this->license()->hostAllowed('www.example.com'));
    }

    public function test_host_allowed_with_aliases(): void
    {
        Setting::set('licensed_domain', 'example.com', 'license');
        Setting::set('license_domain_aliases', 'staging.example.com, app.example.com', 'license');

        $this->assertTrue($this->license()->hostAllowed('staging.example.com'));
        $this->assertTrue($this->license()->hostAllowed('app.example.com'));
        $this->assertFalse($this->license()->hostAllowed('other.example.com'));
    }

    public function test_host_allowed_when_domain_unconfigured(): void
    {
        Setting::set('licensed_domain', '', 'license');

        // Not configured yet — never lock the owner out.
        $this->assertTrue($this->license()->hostAllowed('anything.example'));
        $this->assertNull($this->license()->licensedDomain());
    }

    public function test_kill_switch_toggle(): void
    {
        $this->assertFalse($this->license()->isLocked());

        $this->license()->activateKillSwitch('test');

        $this->assertTrue($this->license()->isLocked());
        $this->assertDatabaseHas('license_violations', [
            'type' => \App\Models\LicenseViolation::TYPE_KILL_SWITCH,
        ]);

        $this->license()->deactivateKillSwitch();

        $this->assertFalse($this->license()->isLocked());
    }

    public function test_remote_kill_token_round_trip(): void
    {
        $token = $this->license()->remoteKillToken();

        $this->assertTrue($this->license()->remoteKillTokenValid($token));
        $this->assertFalse($this->license()->remoteKillTokenValid('wrong'));
        $this->assertFalse($this->license()->remoteKillTokenValid(''));
        $this->assertFalse($this->license()->remoteKillTokenValid(null));

        // Regenerating the secret invalidates old tokens.
        $this->license()->regenerateKillSecret();
        $this->assertFalse($this->license()->remoteKillTokenValid($token));
    }

    public function test_status_reports_state(): void
    {
        Setting::set('app_mode', 'production', 'license');
        Setting::set('license_signature', LicenseService::SIGNATURE, 'license');
        Setting::set('licensed_domain', '', 'license');

        $status = $this->license()->status();

        $this->assertSame('warning', $status['state']);
        $this->assertTrue($status['is_production']);

        Setting::set('licensed_domain', 'example.com', 'license');

        $this->assertSame('ok', $this->license()->status()['state']);

        // Tampered signature in production is a violation state.
        Setting::set('license_signature', 'TAMPERED', 'license');

        $this->assertSame('violation', $this->license()->status()['state']);
    }

    public function test_violation_dedupe_prevents_log_flood(): void
    {
        $request = \Illuminate\Http\Request::create('http://example.com/', 'GET');

        $this->license()->recordViolation('domain_mismatch', $request);
        $this->license()->recordViolation('domain_mismatch', $request);
        $this->license()->recordViolation('domain_mismatch', $request);

        $this->assertSame(1, \App\Models\LicenseViolation::query()->count());
    }
}
