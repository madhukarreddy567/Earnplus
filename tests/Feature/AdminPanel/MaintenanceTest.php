<?php

namespace Tests\Feature\AdminPanel;

use App\Models\Setting;
use Database\Seeders\AdminSeeder;
use Database\Seeders\SettingSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Phase 9: maintenance mode shows the maintenance page to visitors
 * with the custom message, while admins keep full access.
 */
class MaintenanceTest extends TestCase
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
    public function visitors_see_the_maintenance_page_with_custom_message(): void
    {
        Setting::set('maintenance_mode', '1', 'features');
        Setting::set('maintenance_message', 'Back shortly, folks.', 'general');

        $response = $this->get('/');

        $response->assertStatus(503);
        $response->assertSee('right back');
        $response->assertSee('Back shortly, folks.');
    }

    /** @test */
    public function maintenance_does_not_block_admin_routes(): void
    {
        Setting::set('maintenance_mode', '1', 'features');

        $this->get('/admin/login')->assertOk();
    }

    /** @test */
    public function signed_in_admins_bypass_maintenance(): void
    {
        Setting::set('maintenance_mode', '1', 'features');
        $this->loginAsAdmin();

        $this->get('/admin')->assertOk();
        $this->get('/')->assertOk();
    }

    /** @test */
    public function maintenance_off_means_business_as_usual(): void
    {
        Setting::set('maintenance_mode', '0', 'features');

        $this->get('/')->assertOk();
    }
}
