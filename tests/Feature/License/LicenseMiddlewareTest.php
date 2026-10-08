<?php

namespace Tests\Feature\License;

use App\Models\LicenseViolation;
use App\Models\Setting;
use App\Services\LicenseService;
use Database\Seeders\SettingSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Phase 13: EnsureLicensed middleware — development leniency, production
 * domain lock, signature enforcement, kill switch, neutral responses.
 */
class LicenseMiddlewareTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(SettingSeeder::class);
    }

    protected function enableProduction(string $domain = 'example.com'): void
    {
        Setting::set('app_mode', 'production', 'license');
        Setting::set('licensed_domain', $domain, 'license');
        Setting::set('license_signature', LicenseService::SIGNATURE, 'license');
    }

    public function test_development_mode_passes_everything(): void
    {
        $this->get('http://anything.example/')->assertOk();
        $this->get('http://anything.example/dashboard')->assertRedirect(); // auth, not license
    }

    public function test_production_allows_licensed_domain(): void
    {
        $this->enableProduction('example.com');

        $this->get('http://example.com/')->assertOk();
        $this->get('http://www.example.com/')->assertOk();
    }

    public function test_production_allows_alias_domains(): void
    {
        $this->enableProduction('example.com');
        Setting::set('license_domain_aliases', 'staging.example.com', 'license');

        $this->get('http://staging.example.com/')->assertOk();
    }

    public function test_production_blocks_wrong_domain_with_neutral_page(): void
    {
        $this->enableProduction('example.com');

        $response = $this->get('http://evil.com/');

        $response->assertForbidden();
        $response->assertSee('not licensed for this domain');
        // No internals leaked.
        $response->assertDontSee('example.com');
        $response->assertDontSee('HANDRIKAMADHUKARREDDY');

        $this->assertDatabaseHas('license_violations', [
            'type' => LicenseViolation::TYPE_DOMAIN_MISMATCH,
            'host' => 'evil.com',
        ]);
    }

    public function test_production_blocks_wrong_domain_for_json(): void
    {
        $this->enableProduction('example.com');

        $response = $this->getJson('http://evil.com/api/config');

        $response->assertForbidden();
        $response->assertJson(['message' => 'This installation is not licensed.']);
    }

    public function test_signature_tamper_blocks_in_production(): void
    {
        $this->enableProduction('example.com');
        Setting::set('license_signature', 'TAMPERED', 'license');

        $response = $this->get('http://example.com/');

        $response->assertForbidden();
        $response->assertSee('not licensed for this domain');

        $this->assertDatabaseHas('license_violations', [
            'type' => LicenseViolation::TYPE_SIGNATURE_TAMPER,
        ]);
    }

    public function test_signature_tamper_ignored_in_development(): void
    {
        Setting::set('license_signature', 'TAMPERED', 'license');

        $this->get('http://example.com/')->assertOk();
    }

    public function test_kill_switch_locks_user_routes(): void
    {
        app(LicenseService::class)->activateKillSwitch('test');

        $response = $this->get('http://example.com/');

        $response->assertStatus(503);
        $response->assertSee('temporarily locked');
    }

    public function test_kill_switch_returns_json_for_api(): void
    {
        app(LicenseService::class)->activateKillSwitch('test');

        $this->getJson('http://example.com/api/config')
            ->assertStatus(503)
            ->assertJson(['locked' => true]);
    }

    public function test_kill_switch_does_not_block_admin_login(): void
    {
        app(LicenseService::class)->activateKillSwitch('test');

        $this->get('http://example.com/admin/login')->assertOk();
    }

    public function test_health_check_passes_when_locked(): void
    {
        app(LicenseService::class)->activateKillSwitch('test');

        $this->get('http://example.com/health')->assertOk();
    }

    public function test_violation_threshold_hard_blocks_repeat_offenders(): void
    {
        $this->enableProduction('example.com');
        Setting::set('license_violation_block_threshold', '2', 'license');

        // Two mismatches are recorded (dedupe is per type+ip+host+hour, so
        // seed distinct hosts), the third request hits the hard block.
        foreach (['a.evil.com', 'b.evil.com'] as $host) {
            $this->get("http://{$host}/");
        }

        $response = $this->get('http://c.evil.com/');
        $response->assertForbidden();
        $response->assertSee('Access temporarily restricted.');
        $response->assertDontSee('licensed');
    }

    public function test_zero_threshold_disables_hard_block(): void
    {
        $this->enableProduction('example.com');
        Setting::set('license_violation_block_threshold', '0', 'license');

        for ($i = 0; $i < 3; $i++) {
            $this->get("http://evil{$i}.com/")->assertSee('not licensed for this domain');
        }
    }
}
