<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Database\Seeders\SettingSeeder;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;
use App\Models\Setting;

class RegistrationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(SettingSeeder::class);
        Setting::set('email_auth_enabled', '1', 'features');
    }

    public function test_registration_page_renders(): void
    {
        $response = $this->get('/register');

        $response->assertOk();
        $response->assertSee('Create your account');
    }

    public function test_user_can_register_with_valid_data(): void
    {
        Notification::fake();

        $response = $this->post('/register', [
            'name' => 'Madhukar Reddy',
            'email' => 'madhukar@example.com',
            'password' => 'secret123',
            'password_confirmation' => 'secret123',
        ]);

        $response->assertRedirect(route('verification.notice'));
        $this->assertAuthenticated();

        $user = User::where('email', 'madhukar@example.com')->first();
        $this->assertNotNull($user);
        $this->assertTrue(Hash::check('secret123', $user->password));

        Notification::assertSentTo($user, VerifyEmail::class);
    }

    public function test_duplicate_email_is_rejected(): void
    {
        User::factory()->create(['email' => 'taken@example.com']);

        $response = $this->post('/register', [
            'name' => 'Someone Else',
            'email' => 'taken@example.com',
            'password' => 'secret123',
            'password_confirmation' => 'secret123',
        ]);

        $response->assertSessionHasErrors('email');
        $this->assertGuest();
        $this->assertSame(1, User::where('email', 'taken@example.com')->count());
    }

    public function test_weak_password_is_rejected(): void
    {
        $response = $this->post('/register', [
            'name' => 'Weak Pass',
            'email' => 'weak@example.com',
            'password' => 'short',
            'password_confirmation' => 'short',
        ]);

        $response->assertSessionHasErrors('password');
        $this->assertGuest();
        $this->assertDatabaseMissing('users', ['email' => 'weak@example.com']);
    }

    public function test_mismatched_password_confirmation_is_rejected(): void
    {
        $response = $this->post('/register', [
            'name' => 'Mismatch',
            'email' => 'mismatch@example.com',
            'password' => 'secret123',
            'password_confirmation' => 'different456',
        ]);

        $response->assertSessionHasErrors('password');
        $this->assertGuest();
    }

    public function test_mobile_number_is_optional(): void
    {
        $response = $this->post('/register', [
            'name' => 'No Mobile',
            'email' => 'nomobile@example.com',
            'password' => 'secret123',
            'password_confirmation' => 'secret123',
        ]);

        $response->assertRedirect(route('verification.notice'));

        $user = User::where('email', 'nomobile@example.com')->first();
        $this->assertNotNull($user);
        $this->assertNull($user->mobile);
    }

    public function test_mobile_number_is_stored_when_given(): void
    {
        $this->post('/register', [
            'name' => 'With Mobile',
            'email' => 'withmobile@example.com',
            'mobile' => '9876543210',
            'password' => 'secret123',
            'password_confirmation' => 'secret123',
        ]);

        $user = User::where('email', 'withmobile@example.com')->first();
        $this->assertNotNull($user);
        $this->assertSame('9876543210', $user->mobile);
        $this->assertNull($user->mobile_verified_at);
    }

    public function test_unverified_user_cannot_access_dashboard(): void
    {
        $this->post('/register', [
            'name' => 'Fresh User',
            'email' => 'fresh@example.com',
            'password' => 'secret123',
            'password_confirmation' => 'secret123',
        ]);

        $this->assertAuthenticated();

        $response = $this->get('/dashboard');

        $response->assertRedirect(route('verification.notice'));
    }

    public function test_verified_user_can_access_dashboard(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get('/dashboard');

        $response->assertOk();
        // Phase 3 wallet dashboard: balance hero, ₹ equivalent, referral card.
        $response->assertSee('Total balance');
        $response->assertSee($user->fresh()->referral_code);
    }

    public function test_guests_are_redirected_to_login_from_dashboard(): void
    {
        $response = $this->get('/dashboard');

        $response->assertRedirect(route('login'));
    }
}
