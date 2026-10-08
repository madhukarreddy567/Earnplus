<?php

namespace Tests\Feature\Auth;

use App\Models\Setting;
use App\Models\User;
use Database\Seeders\SettingSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The e-mail/password auth toggle (setting `email_auth_enabled`,
 * default OFF). When OFF, every e-mail auth route is gone — GET and
 * POST alike — and no e-mail form/link may appear anywhere in the UI.
 * The admin guard is unaffected.
 */
class EmailAuthToggleTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(SettingSeeder::class);
        // Default from the seeder is OFF — assert that explicitly.
        $this->assertFalse(setting_bool('email_auth_enabled', false));
    }

    public function test_email_routes_404_when_toggle_off(): void
    {
        $this->get('/register')->assertNotFound();
        $this->post('/register', [])->assertNotFound();
        $this->post('/login', ['email' => 'x@y.z', 'password' => 'secret123'])->assertNotFound();
        $this->get('/forgot-password')->assertNotFound();
        $this->post('/forgot-password', ['email' => 'x@y.z'])->assertNotFound();
        $this->get('/reset-password/some-token')->assertNotFound();
        $this->post('/reset-password', [])->assertNotFound();
    }

    public function test_login_page_renders_without_email_form_when_toggle_off(): void
    {
        $response = $this->get('/login');

        $response->assertOk();
        $response->assertSee('Welcome back');
        // No e-mail form, no password field, no registration link.
        $response->assertDontSee('name="email"', false);
        $response->assertDontSee('name="password"', false);
        $response->assertDontSee('Create an account', false);
    }

    public function test_landing_has_no_email_signup_links_when_toggle_off(): void
    {
        $response = $this->get('/');

        $response->assertOk();
        $this->assertStringNotContainsString('/register', $response->getContent());
        $this->assertStringNotContainsString('/login', $response->getContent());
    }

    public function test_email_routes_work_when_toggle_on(): void
    {
        Setting::set('email_auth_enabled', '1', 'features');

        $this->get('/register')->assertOk();
        $this->get('/login')->assertOk()->assertSee('name="email"', false);
        $this->get('/forgot-password')->assertOk();
        $this->get('/reset-password/some-token')->assertOk();

        // And a real registration still works end-to-end.
        $response = $this->post('/register', [
            'name' => 'Toggle On',
            'email' => 'toggle@example.com',
            'password' => 'secret123',
            'password_confirmation' => 'secret123',
        ]);

        $response->assertRedirect(route('verification.notice'));
        $this->assertDatabaseHas('users', ['email' => 'toggle@example.com']);
    }

    public function test_dashboard_referral_share_link_uses_google_when_toggle_off(): void
    {
        $user = User::factory()->create(['email_verified_at' => now()]);

        $response = $this->actingAs($user)->get('/dashboard');

        $response->assertOk();
        $response->assertSee('/auth/google', false);
        $this->assertStringNotContainsString('/register?ref=', $response->getContent());
    }

    public function test_admin_login_unaffected_by_toggle(): void
    {
        // Toggle OFF (default) — admin login must still render and work.
        $this->get('/admin/login')->assertOk()->assertSee('Admin access');
    }
}
