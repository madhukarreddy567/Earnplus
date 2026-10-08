<?php

namespace Tests\Feature\Auth;

use App\Models\OtpCode;
use App\Models\Setting;
use App\Models\User;
use App\Services\OtpService;
use Database\Seeders\SettingSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OtpTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(SettingSeeder::class);
        Setting::set('email_auth_enabled', '1', 'features');
    }

    private function otp(): OtpService
    {
        return app(OtpService::class);
    }

    public function test_otp_step_is_skipped_when_toggle_is_off(): void
    {
        Setting::set('otp_enabled', '0');

        $response = $this->post('/register', [
            'name' => 'Otp Off',
            'email' => 'otpoff@example.com',
            'mobile' => '9876543210',
            'password' => 'secret123',
            'password_confirmation' => 'secret123',
        ]);

        // Goes to e-mail verification, not the OTP page.
        $response->assertRedirect(route('verification.notice'));
        $this->assertDatabaseMissing('otp_codes', [
            'user_id' => User::where('email', 'otpoff@example.com')->first()->id,
        ]);

        // After e-mail verification the dashboard opens with no OTP needed.
        $user = User::where('email', 'otpoff@example.com')->first();
        $user->forceFill(['email_verified_at' => now()])->save();

        // Re-authenticate with a fresh instance: the session guard caches
        // the user model from registration (same-app test artifact).
        $this->actingAs($user->fresh());

        $this->get('/dashboard')->assertOk();
        $this->assertNull($user->fresh()->mobile_verified_at);
    }

    public function test_otp_step_is_required_when_toggle_is_on_with_mobile(): void
    {
        Setting::set('otp_enabled', '1');

        $response = $this->post('/register', [
            'name' => 'Otp On',
            'email' => 'otpon@example.com',
            'mobile' => '9876543210',
            'password' => 'secret123',
            'password_confirmation' => 'secret123',
        ]);

        $response->assertRedirect(route('otp.notice'));

        $user = User::where('email', 'otpon@example.com')->first();
        $this->assertDatabaseHas('otp_codes', ['user_id' => $user->id]);

        // E-mail verification comes first; then the dashboard is gated
        // on the OTP until the mobile number is verified.
        $user->forceFill(['email_verified_at' => now()])->save();
        $this->actingAs($user->fresh());

        $this->get('/dashboard')->assertRedirect(route('otp.notice'));
    }

    public function test_otp_step_is_skipped_without_mobile_even_when_toggle_is_on(): void
    {
        Setting::set('otp_enabled', '1');

        $response = $this->post('/register', [
            'name' => 'No Mobile',
            'email' => 'nomobileotp@example.com',
            'password' => 'secret123',
            'password_confirmation' => 'secret123',
        ]);

        $response->assertRedirect(route('verification.notice'));
    }

    public function test_otp_can_be_verified_with_correct_code(): void
    {
        Setting::set('otp_enabled', '1');

        $user = User::factory()->create([
            'email_verified_at' => now(),
            'mobile' => '9876543210',
        ]);

        $this->otp()->send($user);
        $code = $this->otp()->plainCodeForTest($user);
        $this->assertNotNull($code);

        $response = $this->actingAs($user)->post('/otp/verify', ['code' => $code]);

        $response->assertRedirect(route('dashboard'));
        $this->assertNotNull($user->fresh()->mobile_verified_at);

        // Code is single-use: the record is consumed.
        $this->assertNotNull(OtpCode::where('user_id', $user->id)->first()->consumed_at);

        $this->get('/dashboard')->assertOk();
    }

    public function test_otp_is_rejected_with_wrong_code(): void
    {
        Setting::set('otp_enabled', '1');

        $user = User::factory()->create([
            'email_verified_at' => now(),
            'mobile' => '9876543210',
        ]);

        $this->otp()->send($user);

        $response = $this->actingAs($user)->post('/otp/verify', ['code' => '000000']);

        $response->assertSessionHasErrors('code');
        $this->assertNull($user->fresh()->mobile_verified_at);
    }

    public function test_expired_otp_is_rejected(): void
    {
        Setting::set('otp_enabled', '1');

        $user = User::factory()->create([
            'email_verified_at' => now(),
            'mobile' => '9876543210',
        ]);

        $this->otp()->send($user);
        $code = $this->otp()->plainCodeForTest($user);

        OtpCode::where('user_id', $user->id)->update(['expires_at' => now()->subMinute()]);

        $response = $this->actingAs($user)->post('/otp/verify', ['code' => $code]);

        $response->assertSessionHasErrors('code');
        $this->assertNull($user->fresh()->mobile_verified_at);
    }

    public function test_otp_code_is_stored_hashed_not_plain(): void
    {
        $user = User::factory()->create(['mobile' => '9876543210']);

        $this->otp()->send($user);
        $code = $this->otp()->plainCodeForTest($user);

        $stored = OtpCode::where('user_id', $user->id)->first()->code;

        $this->assertNotSame($code, $stored);
    }

    public function test_otp_can_be_resent(): void
    {
        Setting::set('otp_enabled', '1');

        $user = User::factory()->create([
            'email_verified_at' => now(),
            'mobile' => '9876543210',
        ]);

        $response = $this->actingAs($user)->post('/otp/resend');

        $response->assertSessionHas('status');
        $this->assertDatabaseHas('otp_codes', ['user_id' => $user->id]);
    }

    public function test_otp_page_requires_login(): void
    {
        $response = $this->get('/otp/verify');

        $response->assertRedirect(route('login'));
    }
}
