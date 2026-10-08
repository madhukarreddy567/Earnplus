<?php

namespace Tests\Feature\Auth;

use App\Models\Admin;
use App\Models\User;
use Database\Seeders\AdminSeeder;
use Database\Seeders\SettingSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use App\Models\Setting;

class GuardSeparationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(SettingSeeder::class);
        Setting::set('email_auth_enabled', '1', 'features');
        $this->seed(AdminSeeder::class);
    }

    public function test_user_session_cannot_access_admin_area(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get('/admin');

        $response->assertRedirect(route('admin.login'));
        $this->assertGuest('admin');
    }

    public function test_user_session_cannot_access_admin_management(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get('/admin/admins');

        $response->assertRedirect(route('admin.login'));
    }

    public function test_admin_session_cannot_access_user_dashboard(): void
    {
        // Real admin login (cookie session) — actingAs() with the admin
        // guard would flip the default guard for the test, which never
        // happens in a real browser.
        $this->post('/admin/login', [
            'email' => 'admin@earnplus.local',
            'password' => 'ChangeMe123!',
        ]);

        $this->assertAuthenticated('admin');

        $response = $this->get('/dashboard');

        $response->assertRedirect(route('login'));
        $this->assertGuest(); // web guard stays untouched
    }

    public function test_admin_login_does_not_authenticate_web_guard(): void
    {
        $this->post('/admin/login', [
            'email' => 'admin@earnplus.local',
            'password' => 'ChangeMe123!',
        ]);

        $this->assertAuthenticated('admin');
        $this->assertGuest();
    }

    public function test_user_login_does_not_authenticate_admin_guard(): void
    {
        $user = User::factory()->create();

        $this->post('/login', [
            'email' => $user->email,
            'password' => 'password',
        ]);

        $this->assertAuthenticated();
        $this->assertGuest('admin');
    }

    public function test_admin_cannot_use_user_login_form(): void
    {
        $response = $this->post('/login', [
            'email' => 'admin@earnplus.local',
            'password' => 'ChangeMe123!',
        ]);

        $response->assertSessionHasErrors('email');
        $this->assertGuest();
        $this->assertGuest('admin');
    }

    public function test_user_cannot_use_admin_login_form(): void
    {
        $user = User::factory()->create();

        $response = $this->post('/admin/login', [
            'email' => $user->email,
            'password' => 'password',
        ]);

        $response->assertSessionHasErrors('email');
        $this->assertGuest('admin');
        $this->assertGuest();
    }
}
