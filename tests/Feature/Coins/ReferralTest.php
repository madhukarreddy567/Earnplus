<?php

namespace Tests\Feature\Coins;

use App\Models\CoinTransaction;
use App\Models\Setting;
use App\Models\User;
use App\Services\ReferralService;
use Database\Seeders\SettingSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class ReferralTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(SettingSeeder::class);
        Setting::set('email_auth_enabled', '1', 'features');
        Notification::fake();
    }

    public function test_new_user_gets_unique_referral_code(): void
    {
        $a = User::factory()->create();
        $b = User::factory()->create();

        $this->assertNotEmpty($a->referral_code);
        $this->assertNotEmpty($b->referral_code);
        $this->assertNotSame($a->referral_code, $b->referral_code);
    }

    public function test_register_with_referral_code_credits_referrer(): void
    {
        $referrer = User::factory()->create();

        $response = $this->post('/register', [
            'name' => 'New Friend',
            'email' => 'friend@example.com',
            'password' => 'secret123',
            'password_confirmation' => 'secret123',
            'referral_code' => $referrer->referral_code,
        ]);

        $response->assertRedirect(route('verification.notice'));

        $newUser = User::where('email', 'friend@example.com')->first();
        $this->assertSame($referrer->id, $newUser->referred_by);

        $this->assertSame(25, $referrer->fresh()->coinBalance());
        $this->assertDatabaseHas('coin_transactions', [
            'user_id' => $referrer->id,
            'type' => 'credit',
            'source' => 'referral_bonus',
            'amount' => 25,
        ]);
    }

    public function test_register_with_referral_link_query_prefills_code(): void
    {
        $referrer = User::factory()->create();

        $response = $this->get('/register?ref=' . $referrer->referral_code);

        $response->assertOk();
        $response->assertSee($referrer->referral_code, false);
    }

    public function test_unknown_referral_code_is_ignored(): void
    {
        $response = $this->post('/register', [
            'name' => 'No Ref',
            'email' => 'noref@example.com',
            'password' => 'secret123',
            'password_confirmation' => 'secret123',
            'referral_code' => 'NOTREAL99',
        ]);

        $response->assertRedirect(route('verification.notice'));

        $newUser = User::where('email', 'noref@example.com')->first();
        $this->assertNull($newUser->referred_by);
        $this->assertSame(0, CoinTransaction::count());
    }

    public function test_self_referral_is_blocked(): void
    {
        $user = User::factory()->create();
        $service = app(ReferralService::class);

        $this->expectException(\InvalidArgumentException::class);
        $service->rewardReferrer($user, $user);
    }

    public function test_referral_disabled_toggle_skips_bonus(): void
    {
        Setting::set('referral_enabled', '0', 'features');

        $referrer = User::factory()->create();

        $this->post('/register', [
            'name' => 'Disabled Ref',
            'email' => 'disabledref@example.com',
            'password' => 'secret123',
            'password_confirmation' => 'secret123',
            'referral_code' => $referrer->referral_code,
        ]);

        $newUser = User::where('email', 'disabledref@example.com')->first();
        $this->assertNull($newUser->referred_by);
        $this->assertSame(0, $referrer->fresh()->coinBalance());
    }

    public function test_referral_code_case_insensitive(): void
    {
        $referrer = User::factory()->create();

        $this->post('/register', [
            'name' => 'Lower Case',
            'email' => 'lowercase@example.com',
            'password' => 'secret123',
            'password_confirmation' => 'secret123',
            'referral_code' => strtolower($referrer->referral_code),
        ]);

        $newUser = User::where('email', 'lowercase@example.com')->first();
        $this->assertSame($referrer->id, $newUser->referred_by);
        $this->assertSame(25, $referrer->fresh()->coinBalance());
    }
}
