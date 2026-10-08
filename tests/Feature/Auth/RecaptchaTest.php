<?php

namespace Tests\Feature\Auth;

use App\Models\Setting;
use App\Models\User;
use Database\Seeders\SettingSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class RecaptchaTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(SettingSeeder::class);
        Setting::set('email_auth_enabled', '1', 'features');
    }

    private function enableRecaptcha(bool $googleApproves = true): void
    {
        Setting::set('recaptcha_enabled', '1');
        Setting::set('recaptcha_site_key', 'test-site-key');
        Setting::set('recaptcha_secret_key', 'test-secret-key');

        Http::fake([
            'https://www.google.com/*' => Http::response(['success' => $googleApproves], 200),
        ]);
    }

    public function test_register_works_without_recaptcha_when_disabled(): void
    {
        $response = $this->post('/register', [
            'name' => 'No Captcha',
            'email' => 'nocaptcha@example.com',
            'password' => 'secret123',
            'password_confirmation' => 'secret123',
        ]);

        $response->assertRedirect(route('verification.notice'));
        $this->assertAuthenticated();
    }

    public function test_login_works_without_recaptcha_when_disabled(): void
    {
        $user = User::factory()->create();

        $response = $this->post('/login', [
            'email' => $user->email,
            'password' => 'password',
        ]);

        $response->assertRedirect(route('dashboard'));
        $this->assertAuthenticatedAs($user);
    }

    public function test_register_requires_recaptcha_token_when_enabled(): void
    {
        $this->enableRecaptcha();

        $response = $this->post('/register', [
            'name' => 'Missing Token',
            'email' => 'missingtoken@example.com',
            'password' => 'secret123',
            'password_confirmation' => 'secret123',
        ]);

        $response->assertSessionHasErrors('g-recaptcha-response');
        $this->assertGuest();
        $this->assertDatabaseMissing('users', ['email' => 'missingtoken@example.com']);
    }

    public function test_register_succeeds_with_valid_recaptcha_token(): void
    {
        $this->enableRecaptcha();

        $response = $this->post('/register', [
            'name' => 'With Token',
            'email' => 'withtoken@example.com',
            'password' => 'secret123',
            'password_confirmation' => 'secret123',
            'g-recaptcha-response' => 'valid-test-token',
        ]);

        $response->assertRedirect(route('verification.notice'));
        $this->assertAuthenticated();

        Http::assertSent(function ($request) {
            return $request->url() === 'https://www.google.com/recaptcha/api/siteverify'
                && $request['response'] === 'valid-test-token';
        });
    }

    public function test_register_blocked_when_google_rejects_token(): void
    {
        $this->enableRecaptcha(false);

        $response = $this->post('/register', [
            'name' => 'Bad Token',
            'email' => 'badtoken@example.com',
            'password' => 'secret123',
            'password_confirmation' => 'secret123',
            'g-recaptcha-response' => 'bad-token',
        ]);

        $response->assertSessionHasErrors('g-recaptcha-response');
        $this->assertGuest();
    }

    public function test_login_requires_recaptcha_token_when_enabled(): void
    {
        $this->enableRecaptcha();

        $user = User::factory()->create();

        $response = $this->post('/login', [
            'email' => $user->email,
            'password' => 'password',
        ]);

        $response->assertSessionHasErrors('g-recaptcha-response');
        $this->assertGuest();
    }

    public function test_login_succeeds_with_valid_recaptcha_token(): void
    {
        $this->enableRecaptcha();

        $user = User::factory()->create();

        $response = $this->post('/login', [
            'email' => $user->email,
            'password' => 'password',
            'g-recaptcha-response' => 'valid-test-token',
        ]);

        $response->assertRedirect(route('dashboard'));
        $this->assertAuthenticatedAs($user);
    }

    public function test_recaptcha_widget_renders_only_when_enabled(): void
    {
        $this->get('/register')->assertDontSee('g-recaptcha', false);

        $this->enableRecaptcha();

        $this->get('/register')->assertSee('g-recaptcha', false);
    }
}
