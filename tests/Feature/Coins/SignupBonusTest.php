<?php

namespace Tests\Feature\Coins;

use App\Models\CoinTransaction;
use App\Models\Setting;
use App\Models\User;
use Database\Seeders\SettingSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class SignupBonusTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(SettingSeeder::class);
        Notification::fake();
    }

    protected function verifyUser(User $user): void
    {
        $url = \Illuminate\Support\Facades\URL::temporarySignedRoute(
            'verification.verify',
            now()->addMinutes(60),
            ['id' => $user->id, 'hash' => sha1($user->email)]
        );

        $this->actingAs($user)->get($url);
    }

    public function test_signup_bonus_credited_on_email_verification(): void
    {
        $user = User::factory()->unverified()->create();

        $this->verifyUser($user);

        $this->assertSame(50, $user->fresh()->coinBalance());
        $this->assertDatabaseHas('coin_transactions', [
            'user_id' => $user->id,
            'type' => 'credit',
            'source' => 'signup_bonus',
            'amount' => 50,
        ]);
    }

    public function test_signup_bonus_amount_comes_from_settings(): void
    {
        Setting::set('signup_bonus_coins', '200', 'coins');

        $user = User::factory()->unverified()->create();
        $this->verifyUser($user);

        $this->assertSame(200, $user->fresh()->coinBalance());
    }

    public function test_signup_bonus_never_double_credits(): void
    {
        $user = User::factory()->unverified()->create();

        $this->verifyUser($user);
        // A second visit to the verify route (already verified → dashboard).
        $this->verifyUser($user);

        $this->assertSame(50, $user->fresh()->coinBalance());
        $this->assertSame(1, CoinTransaction::where('user_id', $user->id)
            ->where('source', 'signup_bonus')->count());
    }

    public function test_no_bonus_when_amount_is_zero(): void
    {
        Setting::set('signup_bonus_coins', '0', 'coins');

        $user = User::factory()->unverified()->create();
        $this->verifyUser($user);

        $this->assertSame(0, $user->fresh()->coinBalance());
        $this->assertSame(0, CoinTransaction::where('user_id', $user->id)->count());
    }
}
