<?php

namespace Tests\Feature\Coins;

use App\Models\Setting;
use App\Models\SpinHistory;
use App\Models\User;
use App\Services\SpinService;
use Database\Seeders\SettingSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SpinTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(SettingSeeder::class);
    }

    protected function verifiedUser(): User
    {
        $user = User::factory()->create(['email_verified_at' => now()]);
        $this->actingAs($user);

        return $user;
    }

    public function test_spin_page_renders_when_enabled(): void
    {
        $this->verifiedUser();

        $response = $this->get('/spin');

        $response->assertOk();
        $response->assertSee('Spin wheel');
    }

    public function test_spin_page_404_when_disabled(): void
    {
        Setting::set('spin_enabled', '0', 'features');
        $this->verifiedUser();

        $this->get('/spin')->assertNotFound();
    }

    public function test_guaranteed_win_credits_segment_amount(): void
    {
        Setting::set('spin_win_probability', '100', 'spin');
        Setting::set('spin_min_coins', '10', 'spin');
        Setting::set('spin_max_coins', '80', 'spin');

        $user = $this->verifiedUser();
        $spin = app(SpinService::class);

        $response = $this->postJson('/spin', ['device_fingerprint' => 'fp_test_1']);

        $response->assertOk();
        $data = $response->json();

        $this->assertTrue($data['won']);
        $this->assertContains($data['amount'], $spin->segments());
        $this->assertSame($spin->segments()[$data['segment_index']], $data['amount']);
        $this->assertSame($data['amount'], $data['balance']);
        $this->assertSame(0, $data['spins_left']);

        // Ledger + history rows exist, IP/fingerprint logged.
        $history = SpinHistory::where('user_id', $user->id)->first();
        $this->assertNotNull($history);
        $this->assertTrue($history->won);
        $this->assertSame($data['amount'], $history->result_amount);
        $this->assertSame('fp_test_1', $history->device_fingerprint);
        $this->assertNotEmpty($history->ip);
        $this->assertDatabaseHas('coin_transactions', [
            'user_id' => $user->id,
            'source' => 'spin',
            'amount' => $data['amount'],
            'balance_after' => $data['amount'],
        ]);
    }

    public function test_guaranteed_loss_credits_nothing(): void
    {
        Setting::set('spin_win_probability', '0', 'spin');

        $user = $this->verifiedUser();

        $response = $this->postJson('/spin');

        $response->assertOk();
        $data = $response->json();

        $this->assertFalse($data['won']);
        $this->assertSame(0, $data['amount']);
        $this->assertNull($data['segment_index']);
        $this->assertSame(0, $user->fresh()->coinBalance());
        $this->assertSame(0, \App\Models\CoinTransaction::where('user_id', $user->id)->count());

        $history = SpinHistory::where('user_id', $user->id)->first();
        $this->assertNotNull($history);
        $this->assertFalse($history->won);
    }

    public function test_daily_limit_enforced(): void
    {
        Setting::set('spin_win_probability', '0', 'spin');
        Setting::set('spin_daily_limit', '1', 'spin');

        $this->verifiedUser();

        $this->postJson('/spin')->assertOk();

        $response = $this->postJson('/spin');
        $response->assertStatus(429);
        $response->assertJsonPath('error', 'Daily spin limit reached. Try again tomorrow!');
    }

    public function test_configurable_daily_limit_allows_multiple_spins(): void
    {
        Setting::set('spin_win_probability', '0', 'spin');
        Setting::set('spin_daily_limit', '3', 'spin');

        $user = $this->verifiedUser();
        $spin = app(SpinService::class);

        $this->postJson('/spin')->assertOk();
        $this->postJson('/spin')->assertOk();
        $this->assertSame(1, $spin->spinsLeftToday($user->fresh()));

        $response = $this->postJson('/spin');
        $response->assertOk();
        $this->assertSame(0, $response->json('spins_left'));

        $this->postJson('/spin')->assertStatus(429);
    }

    public function test_spin_disabled_returns_403(): void
    {
        Setting::set('spin_enabled', '0', 'features');
        $this->verifiedUser();

        $response = $this->postJson('/spin');
        $response->assertStatus(403);
        $this->assertSame(0, SpinHistory::count());
    }

    public function test_client_cannot_influence_outcome(): void
    {
        Setting::set('spin_win_probability', '0', 'spin');

        $user = $this->verifiedUser();

        // Attacker tries to smuggle a prize amount / winning segment in.
        $response = $this->postJson('/spin', [
            'amount' => 99999,
            'segment_index' => 7,
            'won' => true,
        ]);

        $response->assertOk();
        $data = $response->json();

        $this->assertFalse($data['won']);
        $this->assertSame(0, $data['amount']);
        $this->assertSame(0, $user->fresh()->coinBalance());
    }

    public function test_win_amount_within_configured_range(): void
    {
        Setting::set('spin_win_probability', '100', 'spin');
        Setting::set('spin_min_coins', '15', 'spin');
        Setting::set('spin_max_coins', '60', 'spin');

        $this->verifiedUser();

        for ($i = 0; $i < 3; $i++) {
            Setting::set('spin_daily_limit', (string) ($i + 1), 'spin');
            $response = $this->postJson('/spin');
            $response->assertOk();
            $amount = $response->json('amount');
            $this->assertGreaterThanOrEqual(15, $amount);
            $this->assertLessThanOrEqual(60, $amount);
        }
    }

    public function test_segments_derive_from_settings(): void
    {
        Setting::set('spin_min_coins', '10', 'spin');
        Setting::set('spin_max_coins', '80', 'spin');

        $segments = app(SpinService::class)->segments();

        $this->assertCount(8, $segments);
        $this->assertSame(10, min($segments));
        $this->assertSame(80, max($segments));
    }
}
