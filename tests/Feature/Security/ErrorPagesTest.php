<?php

namespace Tests\Feature\Security;

use Database\Seeders\SettingSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ErrorPagesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(SettingSeeder::class);
    }

    public function test_404_page_is_branded_and_leaks_nothing(): void
    {
        $response = $this->get('/this-page-does-not-exist-xyz');

        $response->assertNotFound();
        $response->assertSee('Page not found');
        $response->assertDontSee('Exception');
        $response->assertDontSee('Stack trace');
        $response->assertDontSee('laravel.log');
    }

    public function test_403_page_is_neutral(): void
    {
        // A regular user hitting a super-admin-only page gets the 403 page.
        $admin = \App\Models\Admin::create([
            'name' => 'Staff',
            'email' => 'staff@example.com',
            'password' => 'secret123',
            'role' => 'admin',
            'is_active' => true,
        ]);
        $response = $this->actingAs($admin, 'admin')->get('/admin/admins');

        $response->assertForbidden();
        $response->assertSee('Access denied');
        $response->assertDontSee('Exception');
    }

    public function test_429_page_renders_on_throttle(): void
    {
        // Hammer the verification resend (6/min limit) to trigger a 429.
        $user = \App\Models\User::factory()->create(['email_verified_at' => null]);
        $this->actingAs($user);

        for ($i = 0; $i < 6; $i++) {
            $this->post('/email/verification-notification');
        }

        $response = $this->post('/email/verification-notification');
        $response->assertStatus(429);
        $response->assertSee('Too many requests');
    }
}
