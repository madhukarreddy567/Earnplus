<?php

namespace Tests\Feature\Security;

use App\Models\Admin;
use App\Models\Setting;
use Database\Seeders\AdminSeeder;
use Database\Seeders\SettingSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RateLimitingTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(SettingSeeder::class);
        Setting::set('email_auth_enabled', '1', 'features');
        $this->seed(AdminSeeder::class);
    }

    public function test_admin_login_backstop_throttles_floods(): void
    {
        $admin = Admin::query()->first();

        // 30 attempts allowed per minute per IP+e-mail; the 31st is a 429.
        for ($i = 0; $i < 30; $i++) {
            $this->post('/admin/login', [
                'email' => $admin->email,
                'password' => 'wrong-password',
            ]);
        }

        $this->post('/admin/login', [
            'email' => $admin->email,
            'password' => 'wrong-password',
        ])->assertStatus(429);
    }

    public function test_admin_panel_writes_are_throttled(): void
    {
        $admin = Admin::query()->first();
        $this->actingAs($admin, 'admin');

        for ($i = 0; $i < 120; $i++) {
            $this->post(route('admin.security.settings'), []);
        }

        // Validation errors are fine — what matters is the 121st is throttled.
        $this->post(route('admin.security.settings'), [])->assertStatus(429);
    }

    public function test_postback_endpoint_is_throttled_per_ip(): void
    {
        for ($i = 0; $i < 300; $i++) {
            $this->post('/postback/demo', []);
        }

        $this->post('/postback/demo', [])->assertStatus(429);
    }

    public function test_otp_verify_is_throttled(): void
    {
        $user = \App\Models\User::factory()->create(['email_verified_at' => now()]);
        $this->actingAs($user);

        for ($i = 0; $i < 10; $i++) {
            $this->post('/otp/verify', ['code' => '000000']);
        }

        $this->post('/otp/verify', ['code' => '000000'])->assertStatus(429);
    }

    public function test_user_login_backstop_is_per_ip_plus_email(): void
    {
        $user = \App\Models\User::factory()->create(['email' => 'victim@example.com']);
        $other = \App\Models\User::factory()->create(['email' => 'other@example.com']);

        // 30 hits against the victim's e-mail…
        for ($i = 0; $i < 30; $i++) {
            $this->post('/login', [
                'email' => $user->email,
                'password' => 'wrong-password',
            ]);
        }

        // …don't throttle a different e-mail from the same IP.
        $response = $this->post('/login', [
            'email' => $other->email,
            'password' => 'password',
        ]);

        $response->assertRedirect(route('dashboard'));
    }
}
