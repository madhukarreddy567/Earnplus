<?php

namespace Tests\Feature\Api;

use App\Models\CoinTransaction;
use App\Models\OfferwallProvider;
use App\Models\Setting;
use App\Models\User;
use App\Models\WithdrawalMethod;
use App\Services\CoinService;
use Database\Seeders\OfferwallSeeder;
use Database\Seeders\SettingSeeder;
use Database\Seeders\WithdrawalMethodSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Mobile JSON API (Sanctum): auth, wallet, check-in, spin, tasks,
 * promotions, withdrawals, referral.
 */
class ApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(SettingSeeder::class);
        $this->seed(WithdrawalMethodSeeder::class);
    }

    protected function authHeaders(User $user): array
    {
        return ['Authorization' => 'Bearer ' . $user->createToken('test')->plainTextToken];
    }

    // Config -----------------------------------------------------------

    public function test_config_is_public_and_exposes_no_secrets(): void
    {
        $response = $this->getJson('/api/config');

        $response->assertOk()
            ->assertJsonPath('site_name', 'EarnPlus')
            ->assertJsonPath('email_auth_enabled', false)
            ->assertJsonPath('google_auth_enabled', false)
            ->assertJsonStructure(['features', 'branding', 'ads', 'withdraw']);

        $body = $response->json();
        $this->assertStringNotContainsString('secret', json_encode($body));
    }

    // Auth --------------------------------------------------------------

    public function test_guests_cannot_reach_protected_endpoints(): void
    {
        $this->getJson('/api/wallet')->assertUnauthorized();
        $this->getJson('/api/auth/me')->assertUnauthorized();
        $this->postJson('/api/checkin')->assertUnauthorized();
        $this->postJson('/api/spin')->assertUnauthorized();
    }

    public function test_google_exchange_404s_when_not_configured(): void
    {
        $this->postJson('/api/auth/google', ['id_token' => 'x'])
            ->assertNotFound();
    }

    public function test_google_exchange_creates_user_and_returns_token(): void
    {
        Setting::set('google_client_id', 'test-client-id', 'auth');
        Setting::set('google_client_secret', 'test-secret', 'auth');

        Http::fake([
            'oauth2.googleapis.com/*' => Http::response([
                'sub' => 'google-123',
                'email' => 'mobile@example.com',
                'name' => 'Mobile User',
                'picture' => 'https://example.com/pic.png',
                'aud' => 'test-client-id',
            ]),
        ]);

        $response = $this->postJson('/api/auth/google', ['id_token' => 'fake-token']);

        $response->assertOk()
            ->assertJsonStructure(['token', 'user' => ['id', 'name', 'email', 'coins']])
            ->assertJsonPath('user.email', 'mobile@example.com');

        $user = User::where('email', 'mobile@example.com')->first();
        $this->assertNotNull($user);
        $this->assertSame('google-123', $user->google_id);
        $this->assertNotNull($user->email_verified_at);

        // The token actually authenticates.
        $this->getJson('/api/auth/me', $this->authHeaders($user))->assertOk();
    }

    public function test_google_exchange_rejects_wrong_audience(): void
    {
        Setting::set('google_client_id', 'test-client-id', 'auth');
        Setting::set('google_client_secret', 'test-secret', 'auth');

        Http::fake([
            'oauth2.googleapis.com/*' => Http::response([
                'sub' => 'google-123',
                'email' => 'mobile@example.com',
                'aud' => 'someone-elses-client-id',
            ]),
        ]);

        $this->postJson('/api/auth/google', ['id_token' => 'fake-token'])
            ->assertUnauthorized();

        $this->assertNull(User::where('email', 'mobile@example.com')->first());
    }

    public function test_google_exchange_links_existing_email_account(): void
    {
        Setting::set('google_client_id', 'test-client-id', 'auth');
        Setting::set('google_client_secret', 'test-secret', 'auth');

        $existing = User::factory()->create(['email' => 'linkme@example.com']);

        Http::fake([
            'oauth2.googleapis.com/*' => Http::response([
                'sub' => 'google-999',
                'email' => 'linkme@example.com',
                'aud' => 'test-client-id',
            ]),
        ]);

        $this->postJson('/api/auth/google', ['id_token' => 'fake-token'])->assertOk();

        $this->assertSame('google-999', $existing->fresh()->google_id);
        $this->assertSame(1, User::where('email', 'linkme@example.com')->count());
    }

    public function test_email_login_404s_when_disabled(): void
    {
        $this->postJson('/api/auth/login', [
            'email' => 'a@b.c',
            'password' => 'password',
        ])->assertNotFound();
    }

    public function test_email_login_works_when_enabled(): void
    {
        Setting::set('email_auth_enabled', '1', 'auth');
        $user = User::factory()->create(['email' => 'login@example.com']);

        $response = $this->postJson('/api/auth/login', [
            'email' => 'login@example.com',
            'password' => 'password',
        ]);

        $response->assertOk()->assertJsonStructure(['token', 'user']);
        $this->assertSame($user->id, $response->json('user.id'));
    }

    public function test_email_login_rejects_bad_password(): void
    {
        Setting::set('email_auth_enabled', '1', 'auth');
        User::factory()->create(['email' => 'login2@example.com']);

        $this->postJson('/api/auth/login', [
            'email' => 'login2@example.com',
            'password' => 'wrong',
        ])->assertUnprocessable();
    }

    public function test_logout_revokes_token(): void
    {
        $user = User::factory()->create();
        $headers = ['Authorization' => 'Bearer ' . $user->createToken('test')->plainTextToken];

        $this->postJson('/api/auth/logout', [], $headers)->assertOk();
        $this->assertSame(0, $user->fresh()->tokens()->count());

        // Drop the guard's in-test cached user so the next request
        // re-validates the (now deleted) token for real.
        auth()->forgetGuards();

        $this->getJson('/api/auth/me', $headers)->assertUnauthorized();
    }

    // Wallet --------------------------------------------------------------

    public function test_wallet_returns_balance_and_transactions(): void
    {
        $user = User::factory()->create();
        app(CoinService::class)->credit($user, 250, CoinTransaction::SOURCE_SIGNUP_BONUS, 'api-w1');

        $response = $this->getJson('/api/wallet', $this->authHeaders($user));
        $response->assertOk()
            ->assertJsonPath('coins', 250)
            ->assertJsonPath('rupees', 2.5);

        $history = $this->getJson('/api/transactions', $this->authHeaders($user));
        $history->assertOk()
            ->assertJsonPath('data.0.source', CoinTransaction::SOURCE_SIGNUP_BONUS)
            ->assertJsonPath('data.0.amount', 250);
    }

    // Check-in --------------------------------------------------------------

    public function test_checkin_status_and_claim(): void
    {
        $user = User::factory()->create();
        $headers = $this->authHeaders($user);

        $this->getJson('/api/checkin/status', $headers)
            ->assertOk()
            ->assertJsonPath('checked_in_today', false);

        $claim = $this->postJson('/api/checkin', [], $headers);
        $claim->assertOk()->assertJsonStructure(['amount', 'streak', 'balance']);
        $this->assertGreaterThan(0, $claim->json('amount'));

        $this->getJson('/api/checkin/status', $headers)
            ->assertJsonPath('checked_in_today', true);

        // Second claim the same day is refused.
        $this->postJson('/api/checkin', [], $headers)->assertStatus(409);
    }

    // Spin --------------------------------------------------------------

    public function test_spin_status_and_spin(): void
    {
        $user = User::factory()->create();
        $headers = $this->authHeaders($user);

        $status = $this->getJson('/api/spin/status', $headers);
        $status->assertOk()
            ->assertJsonPath('enabled', true)
            ->assertJsonStructure(['segments', 'spins_left', 'daily_limit']);

        $spinsLeft = $status->json('spins_left');
        $this->assertGreaterThan(0, $spinsLeft);

        $spin = $this->postJson('/api/spin', [], $headers);
        $spin->assertOk()->assertJsonStructure([
            'won', 'amount', 'segment_index', 'segments', 'spins_left', 'balance',
        ]);
        $this->assertSame($spinsLeft - 1, $spin->json('spins_left'));
    }

    // Tasks --------------------------------------------------------------

    public function test_tasks_providers_and_click(): void
    {
        $this->seed(OfferwallSeeder::class);
        $user = User::factory()->create();
        $headers = $this->authHeaders($user);

        $providers = $this->getJson('/api/tasks/providers', $headers);
        $providers->assertOk()->assertJsonStructure(['data']);
        $this->assertNotEmpty($providers->json('data'));

        $demo = collect($providers->json('data'))->firstWhere('sandbox', true);
        $this->assertNotNull($demo);

        // A click without a configured wall URL is a clean 422, not a 500.
        $click = $this->postJson("/api/tasks/click/{$demo['slug']}", [], $headers);
        $this->assertContains($click->status(), [200, 422]);
    }

    public function test_tasks_click_unknown_provider_404s(): void
    {
        $user = User::factory()->create();
        $this->postJson('/api/tasks/click/nope', [], $this->authHeaders($user))
            ->assertNotFound();
    }

    // Promotions / referral --------------------------------------------------

    public function test_promotions_empty_when_disabled(): void
    {
        Setting::set('promotions_enabled', '0', 'features');
        $user = User::factory()->create();

        $this->getJson('/api/promotions', $this->authHeaders($user))
            ->assertOk()
            ->assertJsonPath('data', []);
    }

    public function test_referral_returns_code(): void
    {
        $user = User::factory()->create();
        $this->getJson('/api/referral', $this->authHeaders($user))
            ->assertOk()
            ->assertJsonPath('code', $user->referral_code)
            ->assertJsonStructure(['referred_count', 'bonus_coins', 'share_text']);
    }

    // Withdrawals --------------------------------------------------------------

    public function test_withdraw_methods_quote_and_validation(): void
    {
        $user = User::factory()->create();
        app(CoinService::class)->credit($user, 100000, CoinTransaction::SOURCE_SIGNUP_BONUS, 'api-w2');
        $headers = $this->authHeaders($user);

        $methods = $this->getJson('/api/withdraw/methods', $headers);
        $methods->assertOk()->assertJsonStructure(['data', 'presets_paise']);
        $this->assertNotEmpty($methods->json('data'));

        $method = collect($methods->json('data'))->firstWhere('type', 'upi');
        $this->assertNotNull($method);

        $quote = $this->postJson('/api/withdraw/quote', [
            'method_id' => $method['id'],
            'amount' => 1000,
        ], $headers);
        $quote->assertOk()->assertJsonStructure(['coins', 'net_display', 'within_limits']);

        // Invalid details are rejected with 422, not a 500.
        $bad = $this->postJson('/api/withdraw', [
            'method_id' => $method['id'],
            'amount' => 1000,
            'idempotency_key' => (string) \Illuminate\Support\Str::uuid(),
            'details' => ['upi_id' => 'not-a-upi'],
        ], $headers);
        $bad->assertUnprocessable();
    }

    public function test_withdraw_request_and_history(): void
    {
        $user = User::factory()->create();
        app(CoinService::class)->credit($user, 100000, CoinTransaction::SOURCE_SIGNUP_BONUS, 'api-w3');
        $headers = $this->authHeaders($user);

        $methods = $this->getJson('/api/withdraw/methods', $headers)->json('data');
        $method = collect($methods)->firstWhere('type', 'upi');
        $key = (string) \Illuminate\Support\Str::uuid();

        $store = $this->postJson('/api/withdraw', [
            'method_id' => $method['id'],
            'amount' => 1000,
            'idempotency_key' => $key,
            'details' => ['upi_id' => 'user@okhdfc'],
        ], $headers);
        $store->assertCreated()->assertJsonPath('status', 'pending');

        // Same idempotency key → same request, no double debit.
        $balanceAfterFirst = $this->getJson('/api/wallet', $headers)->json('coins');
        $this->postJson('/api/withdraw', [
            'method_id' => $method['id'],
            'amount' => 1000,
            'idempotency_key' => $key,
            'details' => ['upi_id' => 'user@okhdfc'],
        ], $headers)->assertCreated();
        $this->assertSame(
            $balanceAfterFirst,
            $this->getJson('/api/wallet', $headers)->json('coins')
        );

        $history = $this->getJson('/api/withdrawals', $headers);
        $history->assertOk();
        $this->assertCount(1, $history->json('data'));
    }

    public function test_sanctum_acting_as_also_works(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $this->getJson('/api/auth/me')
            ->assertOk()
            ->assertJsonPath('user.id', $user->id);
    }
}
