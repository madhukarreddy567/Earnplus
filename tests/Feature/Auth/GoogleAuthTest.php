<?php

namespace Tests\Feature\Auth;

use App\Models\CoinTransaction;
use App\Models\Setting;
use App\Models\User;
use Database\Seeders\SettingSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Socialite\Contracts\Provider;
use Laravel\Socialite\Contracts\User as SocialiteUser;
use Laravel\Socialite\Facades\Socialite;
use Mockery;
use Tests\TestCase;

/**
 * "Continue with Google" (Laravel Socialite).
 *
 * The feature is inert until Google OAuth credentials are configured;
 * the buttons hide and the routes 404. Socialite is mocked — no real
 * Google round-trip happens in tests.
 */
class GoogleAuthTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(SettingSeeder::class);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    protected function enableGoogle(): void
    {
        Setting::set('google_client_id', 'test-client-id', 'auth');
        Setting::set('google_client_secret', 'test-client-secret', 'auth');
    }

    /**
     * Mock the Socialite Google driver to return a fixed Google user.
     */
    protected function mockGoogleUser(array $attributes = []): void
    {
        $defaults = [
            'id' => 'google-123',
            'email' => 'guser@example.com',
            'name' => 'Google User',
            'avatar' => 'https://example.com/avatar.png',
        ];
        $attributes = array_merge($defaults, $attributes);

        $socialiteUser = Mockery::mock(SocialiteUser::class);
        $socialiteUser->shouldReceive('getId')->andReturn($attributes['id']);
        $socialiteUser->shouldReceive('getEmail')->andReturn($attributes['email']);
        $socialiteUser->shouldReceive('getName')->andReturn($attributes['name']);
        $socialiteUser->shouldReceive('getAvatar')->andReturn($attributes['avatar']);

        $provider = Mockery::mock(Provider::class);
        $provider->shouldReceive('user')->andReturn($socialiteUser);
        $provider->shouldReceive('redirect')
            ->andReturn(redirect('https://accounts.google.test/o/oauth2/auth'));

        Socialite::shouldReceive('driver')->with('google')->andReturn($provider);
    }

    public function test_google_routes_404_when_not_configured(): void
    {
        $this->get('/auth/google')->assertNotFound();
        $this->get('/auth/google/callback')->assertNotFound();
    }

    public function test_login_page_hides_google_button_when_not_configured(): void
    {
        $response = $this->get('/login');

        $response->assertOk();
        $response->assertDontSee('Continue with Google', false);
    }

    public function test_login_page_shows_google_button_when_configured(): void
    {
        $this->enableGoogle();

        $response = $this->get('/login');

        $response->assertOk();
        $response->assertSee('Continue with Google', false);
    }

    public function test_new_google_user_is_created_verified_with_signup_bonus(): void
    {
        $this->enableGoogle();
        $this->mockGoogleUser();

        $response = $this->get('/auth/google/callback');

        $response->assertRedirect(route('dashboard'));
        $this->assertAuthenticated();

        $user = User::where('email', 'guser@example.com')->first();
        $this->assertNotNull($user);
        $this->assertSame('google-123', $user->google_id);
        $this->assertNotNull($user->email_verified_at);
        $this->assertNotEmpty($user->referral_code);

        // Signup bonus credited exactly once through the idempotent key.
        $bonus = setting_int('signup_bonus_coins', 50);
        $this->assertSame(1, CoinTransaction::where('user_id', $user->id)
            ->where('source', CoinTransaction::SOURCE_SIGNUP_BONUS)
            ->count());
        $this->assertSame($bonus, $user->coinBalance());
    }

    public function test_existing_email_user_gets_google_account_linked(): void
    {
        $this->enableGoogle();
        $existing = User::factory()->create(['email' => 'guser@example.com']);
        $this->mockGoogleUser();

        $response = $this->get('/auth/google/callback');

        $response->assertRedirect(route('dashboard'));
        $this->assertAuthenticatedAs($existing);

        // Same row — linked, not duplicated.
        $this->assertSame(1, User::where('email', 'guser@example.com')->count());
        $this->assertSame('google-123', $existing->fresh()->google_id);

        // No second signup bonus for a linked (pre-existing) user.
        $this->assertSame(0, CoinTransaction::where('user_id', $existing->id)
            ->where('source', CoinTransaction::SOURCE_SIGNUP_BONUS)
            ->count());
    }

    public function test_returning_google_user_logs_in(): void
    {
        $this->enableGoogle();
        $user = User::factory()->create([
            'email' => 'guser@example.com',
            'google_id' => 'google-123',
            'email_verified_at' => now(),
        ]);
        $this->mockGoogleUser();

        $response = $this->get('/auth/google/callback');

        $response->assertRedirect(route('dashboard'));
        $this->assertAuthenticatedAs($user);
    }

    public function test_google_signup_applies_referral_code(): void
    {
        $this->enableGoogle();
        $referrer = User::factory()->create();
        $this->mockGoogleUser(['email' => 'referred@example.com', 'id' => 'google-999']);

        // Carry the referral code through the OAuth round-trip.
        $redirect = $this->get('/auth/google?ref=' . $referrer->referral_code);
        $redirect->assertRedirect(); // would go to Google in production

        $response = $this->get('/auth/google/callback');

        $response->assertRedirect(route('dashboard'));

        $newUser = User::where('email', 'referred@example.com')->first();
        $this->assertNotNull($newUser);
        $this->assertSame($referrer->id, $newUser->referred_by);
        $this->assertTrue($referrer->fresh()->coinBalance() > 0);
    }
}
