<?php

namespace Tests\Feature\AdminPanel;

use App\Models\Setting;
use Database\Seeders\AdminSeeder;
use Database\Seeders\SettingSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Phase 9: site settings (general / features / maintenance) —
 * tabs render, values save, validation holds, auth gates hold.
 */
class SiteSettingsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(SettingSeeder::class);
    }

    protected function loginAsAdmin(): void
    {
        $this->seed(AdminSeeder::class);

        $this->post('/admin/login', [
            'email' => 'admin@earnplus.local',
            'password' => 'ChangeMe123!',
        ]);
    }

    /** @test */
    public function guests_are_redirected_to_admin_login(): void
    {
        $this->get('/admin/settings')->assertRedirect(route('admin.login'));
        $this->post('/admin/settings', [])->assertRedirect(route('admin.login'));
    }

    /** @test */
    public function tabs_render_with_current_values(): void
    {
        $this->loginAsAdmin();

        $this->get('/admin/settings')->assertOk()->assertSee('Site settings');
        $this->get('/admin/settings?tab=features')->assertOk()->assertSee('Feature &');
        $this->get('/admin/settings?tab=maintenance')->assertOk()->assertSee('Maintenance mode');
        // Unknown tab falls back to general.
        $this->get('/admin/settings?tab=nope')->assertOk()->assertSee('Site name');
    }

    /** @test */
    public function general_settings_save(): void
    {
        $this->loginAsAdmin();

        $response = $this->post('/admin/settings', [
            'tab' => 'general',
            'site_name' => 'MyRewards',
            'site_tagline' => 'Earn more',
            'support_email' => 'help@example.com',
            'default_currency' => 'USD',
            'default_language' => 'hi',
        ]);

        $response->assertRedirect(route('admin.settings.index', ['tab' => 'general']));
        $this->assertSame('MyRewards', Setting::get('site_name'));
        $this->assertSame('help@example.com', Setting::get('support_email'));
        $this->assertSame('USD', Setting::get('default_currency'));
        $this->assertSame('hi', Setting::get('default_language'));
    }

    /** @test */
    public function general_settings_validate(): void
    {
        $this->loginAsAdmin();

        $this->post('/admin/settings', [
            'tab' => 'general',
            'site_name' => 'MyRewards',
            'support_email' => 'not-an-email',
            'default_currency' => 'EUR',
            'default_language' => 'en',
        ])->assertSessionHasErrors(['support_email', 'default_currency']);
    }

    /** @test */
    public function feature_toggles_save_as_checkboxes(): void
    {
        $this->loginAsAdmin();

        // Present = on, absent = off.
        $this->post('/admin/settings', [
            'tab' => 'features',
            'spin_enabled' => '1',
        ])->assertRedirect();

        $this->assertTrue(Setting::boolean('spin_enabled'));
        // An unchecked toggle that was previously on flips off.
        $this->assertFalse(Setting::boolean('referral_enabled'));
    }

    /** @test */
    public function maintenance_settings_save(): void
    {
        $this->loginAsAdmin();

        $this->post('/admin/settings', [
            'tab' => 'maintenance',
            'maintenance_mode' => '1',
            'maintenance_message' => 'Back in 10 minutes.',
        ])->assertRedirect();

        $this->assertTrue(Setting::boolean('maintenance_mode'));
        $this->assertSame('Back in 10 minutes.', Setting::get('maintenance_message'));
    }

    /** @test */
    public function invalid_tab_cannot_write_arbitrary_keys(): void
    {
        $this->loginAsAdmin();

        $this->post('/admin/settings', [
            'tab' => 'general',
            'site_name' => 'OK',
            'default_currency' => 'INR',
            'default_language' => 'en',
            'evil_key' => 'x',
        ])->assertRedirect();

        $this->assertNull(Setting::where('key', 'evil_key')->value('value'));
    }
}
