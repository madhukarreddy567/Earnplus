<?php

namespace App\Services;

use App\Models\CoinTransaction;
use App\Models\User;
use App\Models\Wallet;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class InsufficientBalanceException extends RuntimeException
{
}

/**
 * The ONLY way coins move in EarnPlus.
 *
 * Every credit/debit runs inside a DB transaction with a row lock on
 * the wallet, writes one append-only ledger row (coin_transactions),
 * and records balance_after so the ledger is always auditable.
 * Double-credit is impossible: an idempotency key is unique, and a
 * repeat call with the same key returns the original transaction.
 */
class CoinService
{
    /**
     * Credit coins to a user.
     *
     * @param  string|null  $idempotencyKey  Repeat calls with the same key return the original transaction.
     */
    public function credit(
        User $user,
        int $amount,
        string $source,
        ?string $idempotencyKey = null,
        ?string $reference = null,
        ?array $meta = null
    ): CoinTransaction {
        $this->validate($amount, $source);

        return DB::transaction(function () use ($user, $amount, $source, $idempotencyKey, $reference, $meta) {
            if ($idempotencyKey !== null) {
                $existing = CoinTransaction::where('idempotency_key', $idempotencyKey)->first();
                if ($existing !== null) {
                    return $existing;
                }
            }

            $wallet = $this->lockWallet($user);

            $wallet->coins += $amount;
            $wallet->lifetime_earned += $amount;
            $wallet->save();

            return CoinTransaction::create([
                'user_id' => $user->id,
                'type' => CoinTransaction::TYPE_CREDIT,
                'amount' => $amount,
                'source' => $source,
                'reference' => $reference,
                'meta' => $meta,
                'balance_after' => $wallet->coins,
                'idempotency_key' => $idempotencyKey,
            ]);
        });
    }

    /**
     * Debit coins from a user. Throws InsufficientBalanceException
     * when the wallet cannot cover the amount.
     */
    public function debit(
        User $user,
        int $amount,
        string $source,
        ?string $idempotencyKey = null,
        ?string $reference = null,
        ?array $meta = null
    ): CoinTransaction {
        $this->validate($amount, $source);

        return DB::transaction(function () use ($user, $amount, $source, $idempotencyKey, $reference, $meta) {
            if ($idempotencyKey !== null) {
                $existing = CoinTransaction::where('idempotency_key', $idempotencyKey)->first();
                if ($existing !== null) {
                    return $existing;
                }
            }

            $wallet = $this->lockWallet($user);

            if ($wallet->coins < $amount) {
                throw new InsufficientBalanceException(
                    "Insufficient balance: has {$wallet->coins}, needs {$amount}."
                );
            }

            $wallet->coins -= $amount;
            $wallet->save();

            return CoinTransaction::create([
                'user_id' => $user->id,
                'type' => CoinTransaction::TYPE_DEBIT,
                'amount' => $amount,
                'source' => $source,
                'reference' => $reference,
                'meta' => $meta,
                'balance_after' => $wallet->coins,
                'idempotency_key' => $idempotencyKey,
            ]);
        });
    }

    /**
     * Lock (or create) the wallet row for update inside a transaction.
     */
    protected function lockWallet(User $user): Wallet
    {
        $wallet = Wallet::where('user_id', $user->id)->lockForUpdate()->first();

        if ($wallet === null) {
            $wallet = Wallet::create(['user_id' => $user->id, 'coins' => 0, 'lifetime_earned' => 0]);
            $wallet = Wallet::where('user_id', $user->id)->lockForUpdate()->first();
        }

        return $wallet;
    }

    protected function validate(int $amount, string $source): void
    {
        if ($amount <= 0) {
            throw new \InvalidArgumentException('Coin amount must be positive.');
        }

        if (! in_array($source, CoinTransaction::SOURCES, true)) {
            throw new \InvalidArgumentException("Unknown coin source: {$source}.");
        }
    }
}
