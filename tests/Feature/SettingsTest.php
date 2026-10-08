<?php

namespace Tests\Feature;

use App\Models\Setting;
use Database\Seeders\SettingSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SettingsTest extends TestCase
{
    use RefreshDatabase;

    public function test_setting_can_be_created_and_read(): void
    {
        Setting::set('demo_key', 'demo_value');

        $this->assertDatabaseHas('settings', ['key' => 'demo_key', 'value' => 'demo_value']);
        $this->assertSame('demo_value', Setting::get('demo_key'));
    }

    public function test_setting_can_be_updated(): void
    {
        Setting::set('demo_key', 'first');
        Setting::set('demo_key', 'second');

        $this->assertSame('second', Setting::get('demo_key'));
        $this->assertSame(1, Setting::where('key', 'demo_key')->count());
    }

    public function test_setting_can_be_deleted(): void
    {
        Setting::set('demo_key', 'demo_value');
        Setting::where('key', 'demo_key')->delete();
        Setting::flushCache('demo_key');

        $this->assertDatabaseMissing('settings', ['key' => 'demo_key']);
        $this->assertNull(Setting::get('demo_key'));
    }

    public function test_setting_helper_returns_default_for_missing_key(): void
    {
        $this->assertSame('fallback', setting('no_such_key', 'fallback'));
        $this->assertNull(setting('no_such_key'));
    }

    public function test_setting_helper_returns_stored_value_over_default(): void
    {
        Setting::set('demo_key', 'stored');

        $this->assertSame('stored', setting('demo_key', 'fallback'));
    }

    public function test_setting_bool_helper_parses_toggle_values(): void
    {
        Setting::set('flag_on', '1');
        Setting::set('flag_off', '0');

        $this->assertTrue(setting_bool('flag_on'));
        $this->assertFalse(setting_bool('flag_off'));
        $this->assertFalse(setting_bool('missing_flag'));
        $this->assertTrue(setting_bool('missing_flag', true));
    }

    public function test_setting_int_helper_casts_values(): void
    {
        Setting::set('coins_per_rupee', '100');

        $this->assertSame(100, setting_int('coins_per_rupee'));
        $this->assertSame(7, setting_int('missing_int', 7));
    }

    public function test_seeder_installs_expected_defaults(): void
    {
        $this->seed(SettingSeeder::class);

        $this->assertSame('EarnPlus', setting('site_name'));
        $this->assertSame(100, setting_int('coins_per_rupee'));
        $this->assertFalse(setting_bool('otp_enabled'));
        $this->assertFalse(setting_bool('recaptcha_enabled'));
        $this->assertTrue(setting_bool('spin_enabled'));
        $this->assertTrue(setting_bool('withdrawals_enabled'));
    }

    public function test_landing_page_renders_with_site_name(): void
    {
        $this->seed(SettingSeeder::class);

        $response = $this->get('/');

        $response->assertOk();
        $response->assertSee('EarnPlus');
    }
}
