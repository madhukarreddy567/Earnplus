<?php

namespace Tests\Feature\Auth;

use App\Models\Admin;
use App\Models\LoginLog;
use App\Services\AdminLockout;
use Database\Seeders\AdminSeeder;
use Database\Seeders\SettingSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;
use App\Models\Setting;

class AdminAuthTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(SettingSeeder::class);
        Setting::set('email_auth_enabled', '1', 'features');
        $this->seed(AdminSeeder::class);
    }

    public function test_admin_login_page_is_separate_from_user_login(): void
    {
        $adminPage = $this->get('/admin/login');
        $adminPage->assertOk();
        $adminPage->assertSee('Admin access');

        $userPage = $this->get('/login');
        $userPage->assertOk();
        $userPage->assertSee('Welcome back');
        $userPage->assertDontSee('Admin access');
    }

    public function test_seeder_creates_super_admin_with_documented_defaults(): void
    {
        $this->assertDatabaseHas('admins', [
            'email' => 'admin@earnplus.local',
            'role' => 'super_admin',
            'is_active' => true,
        ]);

        $admin = Admin::where('email', 'admin@earnplus.local')->first();

        $this->assertTrue($admin->isSuperAdmin());
        $this->assertTrue(Hash::check('ChangeMe123!', $admin->password));
    }

    public function test_super_admin_can_login(): void
    {
        $response = $this->post('/admin/login', [
            'email' => 'admin@earnplus.local',
            'password' => 'ChangeMe123!',
        ]);

        $response->assertRedirect(route('admin.dashboard'));
        $this->assertAuthenticated('admin');
        $this->assertGuest(); // web guard stays untouched
    }

    public function test_successful_admin_login_is_logged_with_ip(): void
    {
        $this->post('/admin/login', [
            'email' => 'admin@earnplus.local',
            'password' => 'ChangeMe123!',
        ]);

        $log = LoginLog::where('guard', 'admin')->latest()->first();

        $this->assertNotNull($log);
        $this->assertSame('admin@earnplus.local', $log->email);
        $this->assertTrue($log->success);
        $this->assertNotNull($log->ip);
    }

    public function test_failed_admin_login_is_rejected_and_logged(): void
    {
        $response = $this->post('/admin/login', [
            'email' => 'admin@earnplus.local',
            'password' => 'wrong-password',
        ]);

        $response->assertSessionHasErrors('email');
        $this->assertGuest('admin');

        $this->assertDatabaseHas('login_logs', [
            'email' => 'admin@earnplus.local',
            'success' => false,
            'guard' => 'admin',
        ]);
    }

    public function test_inactive_admin_cannot_login(): void
    {
        Admin::create([
            'name' => 'Inactive',
            'email' => 'inactive@example.com',
            'password' => 'secret123',
            'role' => 'admin',
            'is_active' => false,
        ]);

        $response = $this->post('/admin/login', [
            'email' => 'inactive@example.com',
            'password' => 'secret123',
        ]);

        $response->assertSessionHasErrors('email');
        $this->assertGuest('admin');
    }

    public function test_account_locks_after_eight_failed_attempts(): void
    {
        $email = 'admin@earnplus.local';

        for ($i = 0; $i < 8; $i++) {
            $this->post('/admin/login', [
                'email' => $email,
                'password' => 'wrong-password',
            ]);
        }

        $this->assertTrue(AdminLockout::isLocked($email));
        $this->assertSame(8, AdminLockout::attempts($email));

        // Even the correct password is rejected while locked.
        $response = $this->post('/admin/login', [
            'email' => $email,
            'password' => 'ChangeMe123!',
        ]);

        $response->assertSessionHasErrors('email');
        $this->assertGuest('admin');
        $this->assertStringContainsString(
            'locked',
            strtolower(session('errors')->get('email')[0])
        );

        // All attempts were logged.
        $this->assertSame(9, LoginLog::where('email', $email)->where('success', false)->count());
    }

    public function test_successful_login_clears_failure_count(): void
    {
        $email = 'admin@earnplus.local';

        for ($i = 0; $i < 3; $i++) {
            $this->post('/admin/login', ['email' => $email, 'password' => 'wrong']);
        }

        $this->assertSame(3, AdminLockout::attempts($email));

        $this->post('/admin/login', ['email' => $email, 'password' => 'ChangeMe123!']);

        $this->assertAuthenticated('admin');
        $this->assertSame(0, AdminLockout::attempts($email));
        $this->assertFalse(AdminLockout::isLocked($email));
    }

    public function test_super_admin_can_view_admins_list(): void
    {
        $this->post('/admin/login', [
            'email' => 'admin@earnplus.local',
            'password' => 'ChangeMe123!',
        ]);

        $response = $this->get('/admin/admins');

        $response->assertOk();
        $response->assertSee('admin@earnplus.local');
    }

    public function test_plain_admin_role_cannot_view_admins_list(): void
    {
        Admin::create([
            'name' => 'Staff',
            'email' => 'staff@example.com',
            'password' => 'secret123',
            'role' => 'admin',
            'is_active' => true,
        ]);

        $this->post('/admin/login', [
            'email' => 'staff@example.com',
            'password' => 'secret123',
        ]);

        $this->assertAuthenticated('admin');

        // Same admin area opens fine...
        $this->get('/admin')->assertOk();

        // ...but admin management is forbidden.
        $this->get('/admin/admins')->assertForbidden();
    }

    public function test_admin_can_logout(): void
    {
        $admin = Admin::where('email', 'admin@earnplus.local')->first();
        $this->actingAs($admin, 'admin');

        $response = $this->post('/admin/logout');

        $response->assertRedirect(route('admin.login'));
        $this->assertGuest('admin');
    }

    public function test_guests_are_redirected_to_admin_login_from_admin_area(): void
    {
        $response = $this->get('/admin');

        $response->assertRedirect(route('admin.login'));
    }

    public function test_logged_in_admin_is_redirected_away_from_admin_login_page(): void
    {
        $admin = Admin::where('email', 'admin@earnplus.local')->first();
        $this->actingAs($admin, 'admin');

        $response = $this->get('/admin/login');

        $response->assertRedirect(route('admin.dashboard'));
    }
}
