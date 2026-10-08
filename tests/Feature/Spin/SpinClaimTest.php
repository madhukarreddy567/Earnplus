<?php

namespace Tests\Feature\Spin;

use App\Models\AdNetwork;
use App\Models\CoinTransaction;
use App\Models\Setting;
use App\Models\SpinClaim;
use App\Models\User;
use Database\Seeders\AdSeeder;
use Database\Seeders\SettingSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Spin reward gating: POST /api/spin mints a single-use, expiring claim
 * token WITHOUT crediting; POST /api/spin/claim credits ONLY after
 * Unity's signed S2S callback verifies the rewarded-ad view for it.
 */
class SpinClaimTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(SettingSeeder::class);
        $this->seed(AdSeeder::class);

        // Deterministic spin: always wins a fixed 50 coins.
        Setting::set('spin_win_probability', '100', 'spin');
        Setting::set('spin_min_coins', '50', 'spin');
        Setting::set('spin_max_coins', '50', 'spin');
        Setting::set('spin_daily_limit', '5', 'spin');
        Setting::set('spin_ad_gate_enabled', '1', 'spin');
    }

    protected function userWithHeaders(): array
    {
        $user = User::factory()->create(['email_verified_at' => now()]);
        $headers = ['Authorization' => 'Bearer ' . $user->createToken('test')->plainTextToken];

        return [$user, $headers];
    }

    protected function spin(array $headers): array
    {
        return $this->postJson('/api/spin', [], $headers)->assertOk()->json();
    }

    /**
     * Simulate Unity's signed server-to-server callback for a spin claim.
     * Unity echoes our serverId back as `oid`. $signSecret defaults to the
     * stored secret; pass a different one to simulate tampering.
     */
    protected function unitySpinCallback(User $user, string $token, string $sid = 'unity-sid-1', ?string $signSecret = null)
    {
        $storedSecret = 'test-secret';
        $network = AdNetwork::where('slug', 'unity-ads')->firstOrFail();
        $config = $network->config;
        $config['s2s_secret'] = $storedSecret;
        $network->update(['config' => $config, 'enabled' => true]);

        $params = ['oid' => "{$user->id}:spin:{$token}", 'sid' => $sid];
        ksort($params);
        $params['hmac'] = hash_hmac('sha256', http_build_query($params), $signSecret ?? $storedSecret);

        return $this->getJson('/ads/verify/unity-ads?' . http_build_query($params));
    }

    public function test_spin_win_creates_pending_claim_without_credit(): void
    {
        [$user, $headers] = $this->userWithHeaders();

        $data = $this->spin($headers);

        $this->assertTrue($data['won']);
        $this->assertSame(50, $data['amount']);
        $this->assertNotEmpty($data['claim_token']);
        $this->assertMatchesRegularExpression('/^[0-9a-f]{64}$/', $data['claim_token']);
        $this->assertNotEmpty($data['claim_expires_at']);

        // No credit happened: balance untouched, no ledger row.
        $this->assertSame(0, $data['balance']);
        $this->assertSame(0, $user->fresh()->coinBalance());
        $this->assertSame(0, CoinTransaction::count());

        $claim = SpinClaim::where('token', $data['claim_token'])->firstOrFail();
        $this->assertSame(SpinClaim::STATUS_PENDING, $claim->status);
        $this->assertSame(50, $claim->amount);
        $this->assertNull($claim->unity_verified_at);
    }

    public function test_loss_spin_has_no_claim_token(): void
    {
        Setting::set('spin_win_probability', '0', 'spin');
        [$user, $headers] = $this->userWithHeaders();

        $data = $this->spin($headers);

        $this->assertFalse($data['won']);
        $this->assertNull($data['claim_token']);
        $this->assertSame(0, SpinClaim::count());
    }

    public function test_claim_without_verified_ad_is_rejected(): void
    {
        [$user, $headers] = $this->userWithHeaders();
        $data = $this->spin($headers);

        $response = $this->postJson('/api/spin/claim', ['claim_token' => $data['claim_token']], $headers);

        $response->assertStatus(422)->assertJsonPath('message', 'Watch the reward video to claim your coins.');
        $this->assertSame(0, $user->fresh()->coinBalance());
        $this->assertSame(0, CoinTransaction::count());
    }

    public function test_claim_after_verified_ad_credits_exactly_once(): void
    {
        [$user, $headers] = $this->userWithHeaders();
        $data = $this->spin($headers);

        // Unity's S2S callback arrives (signed) — marks the claim verified.
        $this->unitySpinCallback($user, $data['claim_token'])
            ->assertOk()
            ->assertJsonPath('status', 'spin_claim_verified');

        // Now the claim credits.
        $claim = $this->postJson('/api/spin/claim', ['claim_token' => $data['claim_token']], $headers);
        $claim->assertOk()
            ->assertJsonPath('coins', 50)
            ->assertJsonPath('balance', 50);

        $this->assertSame(50, $user->fresh()->coinBalance());
        $this->assertSame(1, CoinTransaction::where('source', CoinTransaction::SOURCE_SPIN)->count());

        // Replay of the same token is rejected — no double credit.
        $this->postJson('/api/spin/claim', ['claim_token' => $data['claim_token']], $headers)
            ->assertStatus(422);
        $this->assertSame(50, $user->fresh()->coinBalance());
        $this->assertSame(1, CoinTransaction::where('source', CoinTransaction::SOURCE_SPIN)->count());
    }

    public function test_tampered_s2s_signature_never_verifies(): void
    {
        [$user, $headers] = $this->userWithHeaders();
        $data = $this->spin($headers);

        $this->unitySpinCallback($user, $data['claim_token'], 'sid-x', 'wrong-secret')
            ->assertStatus(401);

        $this->assertNull(SpinClaim::where('token', $data['claim_token'])->firstOrFail()->unity_verified_at);

        $this->postJson('/api/spin/claim', ['claim_token' => $data['claim_token']], $headers)
            ->assertStatus(422);
        $this->assertSame(0, $user->fresh()->coinBalance());
    }

    public function test_s2s_callback_for_unknown_token_does_not_credit(): void
    {
        [$user, $headers] = $this->userWithHeaders();

        $this->unitySpinCallback($user, bin2hex(random_bytes(32)))
            ->assertOk()
            ->assertJsonPath('status', 'spin_claim_not_found');

        $this->assertSame(0, CoinTransaction::count());
    }

    public function test_expired_claim_is_rejected(): void
    {
        [$user, $headers] = $this->userWithHeaders();
        $data = $this->spin($headers);

        SpinClaim::where('token', $data['claim_token'])->update([
            'expires_at' => now()->subMinute(),
        ]);

        $this->postJson('/api/spin/claim', ['claim_token' => $data['claim_token']], $headers)
            ->assertStatus(422)
            ->assertJsonPath('message', 'This reward claim has expired. Spin again!');

        $this->assertSame(0, $user->fresh()->coinBalance());
    }

    public function test_other_users_token_is_not_found(): void
    {
        [$user, $headers] = $this->userWithHeaders();
        $data = $this->spin($headers);

        // Fresh guard: Laravel's test client caches the first request's
        // authenticated user on the shared guard instance.
        $this->app['auth']->forgetGuards();
        [$other, $otherHeaders] = $this->userWithHeaders();

        $this->postJson('/api/spin/claim', ['claim_token' => $data['claim_token']], $otherHeaders)
            ->assertNotFound();
        $this->assertSame(0, $other->fresh()->coinBalance());
    }

    public function test_gate_disabled_claims_without_ad_view(): void
    {
        Setting::set('spin_ad_gate_enabled', '0', 'spin');
        [$user, $headers] = $this->userWithHeaders();
        $data = $this->spin($headers);

        $this->postJson('/api/spin/claim', ['claim_token' => $data['claim_token']], $headers)
            ->assertOk()
            ->assertJsonPath('coins', 50);

        $this->assertSame(50, $user->fresh()->coinBalance());
    }

    public function test_claim_status_endpoint_reflects_verification(): void
    {
        [$user, $headers] = $this->userWithHeaders();
        $data = $this->spin($headers);

        $before = $this->getJson('/api/spin/claim/' . $data['claim_token'], $headers)
            ->assertOk()->json();
        $this->assertFalse($before['ad_verified']);
        $this->assertFalse($before['claimed']);

        $this->unitySpinCallback($user, $data['claim_token'])->assertOk();

        $after = $this->getJson('/api/spin/claim/' . $data['claim_token'], $headers)
            ->assertOk()->json();
        $this->assertTrue($after['ad_verified']);
        $this->assertSame('verified', $after['status']);
    }

    public function test_claim_attempts_are_counted(): void
    {
        [$user, $headers] = $this->userWithHeaders();
        $data = $this->spin($headers);

        $this->postJson('/api/spin/claim', ['claim_token' => $data['claim_token']], $headers)->assertStatus(422);
        $this->postJson('/api/spin/claim', ['claim_token' => $data['claim_token']], $headers)->assertStatus(422);

        $claim = SpinClaim::where('token', $data['claim_token'])->firstOrFail();
        $this->assertSame(2, $claim->attempts);
        $this->assertNotNull($claim->last_attempt_at);
    }
}
