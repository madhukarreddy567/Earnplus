<?php

namespace Tests\Feature\Coins;

use App\Models\CoinTransaction;
use App\Models\User;
use App\Services\CoinService;
use App\Services\InsufficientBalanceException;
use Database\Seeders\SettingSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CoinServiceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(SettingSeeder::class);
    }

    public function test_credit_creates_wallet_and_ledger_row(): void
    {
        $user = User::factory()->create();
        $service = app(CoinService::class);

        $tx = $service->credit($user, 100, CoinTransaction::SOURCE_SIGNUP_BONUS, 'key-1');

        $this->assertSame(100, $user->fresh()->coinBalance());
        $this->assertSame(100, $tx->balance_after);
        $this->assertSame('credit', $tx->type);
        $this->assertSame(100, $user->wallet->fresh()->lifetime_earned);
        $this->assertDatabaseHas('coin_transactions', [
            'user_id' => $user->id,
            'type' => 'credit',
            'amount' => 100,
            'source' => 'signup_bonus',
            'balance_after' => 100,
            'idempotency_key' => 'key-1',
        ]);
    }

    public function test_idempotency_key_prevents_double_credit(): void
    {
        $user = User::factory()->create();
        $service = app(CoinService::class);

        $first = $service->credit($user, 50, CoinTransaction::SOURCE_DAILY_CHECKIN, 'idem-abc');
        $second = $service->credit($user, 50, CoinTransaction::SOURCE_DAILY_CHECKIN, 'idem-abc');

        $this->assertSame($first->id, $second->id);
        $this->assertSame(50, $user->fresh()->coinBalance());
        $this->assertSame(1, CoinTransaction::where('user_id', $user->id)->count());
    }

    public function test_debit_reduces_balance_and_keeps_lifetime(): void
    {
        $user = User::factory()->create();
        $service = app(CoinService::class);
        $service->credit($user, 100, CoinTransaction::SOURCE_SPIN, 'c1');

        $tx = $service->debit($user, 40, CoinTransaction::SOURCE_WITHDRAWAL, 'd1');

        $this->assertSame(60, $user->fresh()->coinBalance());
        $this->assertSame(60, $tx->balance_after);
        $this->assertSame('debit', $tx->type);
        // Lifetime earned only grows on credits.
        $this->assertSame(100, $user->wallet->fresh()->lifetime_earned);
    }

    public function test_debit_throws_on_insufficient_balance(): void
    {
        $user = User::factory()->create();
        $service = app(CoinService::class);
        $service->credit($user, 30, CoinTransaction::SOURCE_SPIN, 'c1');

        $this->expectException(InsufficientBalanceException::class);

        $service->debit($user, 31, CoinTransaction::SOURCE_WITHDRAWAL, 'd1');
    }

    public function test_failed_debit_leaves_balance_untouched(): void
    {
        $user = User::factory()->create();
        $service = app(CoinService::class);
        $service->credit($user, 30, CoinTransaction::SOURCE_SPIN, 'c1');

        try {
            $service->debit($user, 99, CoinTransaction::SOURCE_WITHDRAWAL, 'd1');
        } catch (InsufficientBalanceException) {
            // expected
        }

        $this->assertSame(30, $user->fresh()->coinBalance());
        $this->assertSame(1, CoinTransaction::where('user_id', $user->id)->count());
    }

    public function test_non_positive_amount_is_rejected(): void
    {
        $user = User::factory()->create();
        $service = app(CoinService::class);

        $this->expectException(\InvalidArgumentException::class);
        $service->credit($user, 0, CoinTransaction::SOURCE_SPIN);
    }

    public function test_unknown_source_is_rejected(): void
    {
        $user = User::factory()->create();
        $service = app(CoinService::class);

        $this->expectException(\InvalidArgumentException::class);
        $service->credit($user, 10, 'made_up_source');
    }

    public function test_ledger_balances_reconcile_with_wallet(): void
    {
        $user = User::factory()->create();
        $service = app(CoinService::class);

        $service->credit($user, 100, CoinTransaction::SOURCE_SIGNUP_BONUS, 'k1');
        $service->credit($user, 25, CoinTransaction::SOURCE_REFERRAL_BONUS, 'k2');
        $service->debit($user, 30, CoinTransaction::SOURCE_WITHDRAWAL, 'k3');

        $credits = CoinTransaction::where('user_id', $user->id)->where('type', 'credit')->sum('amount');
        $debits = CoinTransaction::where('user_id', $user->id)->where('type', 'debit')->sum('amount');

        $this->assertSame($credits - $debits, $user->fresh()->coinBalance());
    }
}
