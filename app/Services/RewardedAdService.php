<?php

namespace App\Services;

use App\Models\AdPlacement;
use App\Models\AdReward;
use App\Models\CoinTransaction;
use App\Models\Promotion;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Rewarded-ad claims ("watch an ad, earn coins").
 *
 * The front-end shows a countdown timer, but the server NEVER trusts it:
 * every claim is re-checked here — placement must be an enabled rewarded
 * placement, the user must be under the daily limit, a server-side minimum
 * interval must have passed since their last reward, and velocity /
 * shared-device abuse checks run on every claim.
 *
 * Coins move only through CoinService with a deterministic idempotency key
 * `rewarded:{user}:{placement}:{date}:{n}`, so a retried request can never
 * double-credit.
 */
class RewardedAdService
{
    public function __construct(
        protected CoinService $coins,
        protected AdService $ads,
        protected OfferwallService $offerwalls,
        protected PromotionService $promotions
    ) {
    }

    /**
     * Attempt a rewarded-ad claim.
     *
     * @return array{status: string, http: int, message?: string, coins?: int, balance?: int}
     */
    public function claim(AdPlacement $placement, User $user, Request $request): array
    {
        if (! $placement->isRewarded()) {
            return $this->denied(403, 'This placement is not a rewarded ad.');
        }

        if (! $placement->isServable()) {
            return $this->denied(403, 'This rewarded ad is currently disabled.');
        }

        if ($placement->coins <= 0) {
            return $this->denied(403, 'This rewarded ad has no coin reward configured.');
        }

        $ip = $request->ip();
        $userAgent = $request->userAgent();
        $fingerprint = $this->offerwalls->fingerprint($userAgent, $ip);

        // Daily limit per user.
        $dailyLimit = setting_int('rewarded_ad_daily_limit', 10);
        $todayCount = AdReward::where('user_id', $user->id)
            ->whereDate('created_at', today())
            ->count();

        if ($todayCount >= $dailyLimit) {
            return $this->denied(429, 'Daily rewarded-ad limit reached. Come back tomorrow.');
        }

        // Server-side minimum interval between rewards — the client
        // countdown timer is cosmetic; this is the real gate.
        $minInterval = setting_int('rewarded_ad_min_interval_seconds', 20);
        $lastReward = AdReward::where('user_id', $user->id)->latest('id')->first();

        if ($lastReward !== null && $lastReward->created_at->diffInSeconds(now()) < $minInterval) {
            return $this->denied(429, 'Please wait a moment before claiming another reward.');
        }

        // Velocity: max rewards per hour per user.
        $maxPerHour = setting_int('rewarded_ad_max_per_hour', 5);
        $recentCount = AdReward::where('user_id', $user->id)
            ->where('created_at', '>', now()->subHour())
            ->count();

        if ($recentCount >= $maxPerHour) {
            return $this->denied(429, 'Too many rewarded ads claimed — please slow down.');
        }

        // Shared device: same fingerprint earning across >3 users in 24h.
        $distinctUsers = AdReward::where('created_at', '>', now()->subDay())
            ->distinct()
            ->pluck('user_id')
            ->filter(fn ($id) => $this->userFingerprintMatches((int) $id, $fingerprint))
            ->count();

        if ($distinctUsers > 3) {
            return $this->denied(429, 'This device has reached the rewarded-ad limit.');
        }

        // Session frequency cap also applies to rewarded claims.
        $caps = $this->ads->sessionCaps($request);
        $shown = $caps[$placement->id] ?? 0;

        if ($shown >= max(1, $placement->frequency_cap_per_session)) {
            return $this->denied(429, 'No more rewards available for this ad right now.');
        }

        $date = today()->toDateString();
        $n = AdReward::where('user_id', $user->id)
            ->where('placement_id', $placement->id)
            ->whereDate('created_at', today())
            ->count() + 1;
        $idempotencyKey = "rewarded:{$user->id}:{$placement->id}:{$date}:{$n}";

        return DB::transaction(function () use (
            $placement, $user, $request, $ip, $fingerprint, $idempotencyKey
        ) {
            $existing = AdReward::where('idempotency_key', $idempotencyKey)->first();

            if ($existing !== null) {
                $balance = $user->wallet?->coins ?? 0;

                return [
                    'status' => 'ok',
                    'http' => 200,
                    'coins' => $existing->coins,
                    'balance' => (int) $balance,
                ];
            }

            // Promotions multiply the placement's coin reward. Untouched
            // when nothing is active.
            $boost = $this->promotions->applyMultipliers($placement->coins, Promotion::SCOPE_REWARDED_AD);
            $finalCoins = $boost['coins'];

            $meta = [
                'placement_id' => $placement->id,
                'network_id' => $placement->network_id,
                'ip' => $ip,
                'device_fingerprint' => $fingerprint,
            ];
            if ($boost['promotions']->isNotEmpty()) {
                $meta['promotion_ids'] = $this->promotions->promotionIds($boost['promotions']);
                $meta['base_amount'] = $placement->coins;
            }

            $tx = $this->coins->credit(
                $user,
                $finalCoins,
                CoinTransaction::SOURCE_REWARDED_AD,
                $idempotencyKey,
                "ad_reward:{$placement->slug}",
                $meta
            );

            try {
                AdReward::create([
                    'user_id' => $user->id,
                    'placement_id' => $placement->id,
                    'coins' => $finalCoins,
                    'idempotency_key' => $idempotencyKey,
                ]);
            } catch (\Illuminate\Database\QueryException $e) {
                // Lost a race with a concurrent claim for the same key:
                // the row already exists, so treat it as the duplicate.
                if (! $this->isDuplicateKeyError($e)) {
                    throw $e;
                }
            }

            $this->ads->recordImpression(
                $placement,
                $user,
                $request,
                (string) ($request->route()?->getName() ?? $request->path()),
                $this->ads->detectDevice($request->userAgent()),
                true
            );

            return [
                'status' => 'ok',
                'http' => 200,
                'coins' => $tx->amount,
                'balance' => (int) $tx->balance_after,
            ];
        });
    }

    /**
     * Does this user have at least one recent reward earned from the same
     * device fingerprint? (Fingerprint is stored in the coin transaction
     * meta at credit time.)
     */
    protected function userFingerprintMatches(int $userId, string $fingerprint): bool
    {
        return CoinTransaction::where('user_id', $userId)
            ->where('source', CoinTransaction::SOURCE_REWARDED_AD)
            ->where('created_at', '>', now()->subDay())
            ->whereJsonContains('meta->device_fingerprint', $fingerprint)
            ->exists();
    }

    /**
     * @return array{status: string, http: int, message: string}
     */
    protected function denied(int $http, string $message): array
    {
        return ['status' => 'denied', 'http' => $http, 'message' => $message];
    }

    protected function isDuplicateKeyError(\Illuminate\Database\QueryException $e): bool
    {
        // MySQL 1062 / SQLSTATE 23000: duplicate entry for a unique key.
        return (int) $e->errorInfo[1] === 1062
            || str_contains((string) $e->getMessage(), 'Duplicate entry');
    }
}
