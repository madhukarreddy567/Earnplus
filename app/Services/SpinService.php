<?php

namespace App\Services;

use App\Models\CoinTransaction;
use App\Models\Promotion;
use App\Models\SpinClaim;
use App\Models\SpinHistory;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

class SpinDisabledException extends \RuntimeException
{
}

class SpinLimitReachedException extends \RuntimeException
{
}

/**
 * Spin wheel. The outcome is decided 100% server-side with
 * cryptographically secure randomness — the client only animates
 * the result the server returns. All admin knobs (enable, coin
 * range, daily limit, win probability) live in DB settings.
 */
class SpinService
{
    /** Number of prize segments drawn on the wheel. */
    public const SEGMENT_COUNT = 8;

    public function __construct(
        protected CoinService $coins,
        protected PromotionService $promotions,
        protected SpinClaimService $claims
    ) {
    }

    public function enabled(): bool
    {
        return setting_bool('spin_enabled', true);
    }

    public function dailyLimit(): int
    {
        return max(1, setting_int('spin_daily_limit', 1));
    }

    public function spinsUsedToday(User $user): int
    {
        $start = CarbonImmutable::now(config('app.timezone'))->startOfDay();

        return SpinHistory::where('user_id', $user->id)
            ->where('created_at', '>=', $start)
            ->count();
    }

    public function spinsLeftToday(User $user): int
    {
        return max(0, $this->dailyLimit() - $this->spinsUsedToday($user));
    }

    /**
     * The 8 prize values shown on the wheel, derived from the
     * admin-configured min/max (evenly spaced, ascending).
     *
     * @return list<int>
     */
    public function segments(): array
    {
        $min = max(1, setting_int('spin_min_coins', 5));
        $max = max($min, setting_int('spin_max_coins', 100));

        $segments = [];
        for ($i = 0; $i < self::SEGMENT_COUNT; $i++) {
            $segments[] = (int) round($min + ($max - $min) * $i / (self::SEGMENT_COUNT - 1));
        }

        // De-duplicate and re-sort when the range is narrower than
        // the segment count, then pad back up with the max prize.
        $segments = array_values(array_unique($segments));
        sort($segments);

        while (count($segments) < self::SEGMENT_COUNT) {
            $segments[] = $max;
        }

        return array_slice($segments, 0, self::SEGMENT_COUNT);
    }

    /**
     * Perform one spin. Server decides the outcome; the returned
     * segment index tells the client where to land the wheel.
     *
     * When $deferCredit is true (mobile API), a win mints a single-use
     * SpinClaim instead of crediting immediately — the reward is only
     * credited by SpinClaimService::claim() after a verified Unity
     * rewarded-ad view. The web flow keeps immediate credit.
     *
     * @return array{history: SpinHistory, won: bool, amount: int, segments: list<int>, segment_index: int|null, spins_left: int, claim_token: string|null, claim_expires_at: string|null}
     */
    public function spin(User $user, ?string $ip = null, ?string $deviceFingerprint = null, bool $deferCredit = false): array
    {
        if (! $this->enabled()) {
            throw new SpinDisabledException('The spin wheel is currently disabled.');
        }

        return DB::transaction(function () use ($user, $ip, $deviceFingerprint, $deferCredit) {
            if ($this->spinsUsedToday($user) >= $this->dailyLimit()) {
                throw new SpinLimitReachedException('Daily spin limit reached.');
            }

            $segments = $this->segments();
            $probability = min(100, max(0, setting_int('spin_win_probability', 40)));

            $won = random_int(1, 100) <= $probability;
            $segmentIndex = null;
            $amount = 0;
            $claimToken = null;
            $claimExpiresAt = null;

            if ($won) {
                $segmentIndex = random_int(0, count($segments) - 1);
                $amount = $segments[$segmentIndex];
            }

            $history = SpinHistory::create([
                'user_id' => $user->id,
                'result_amount' => $amount,
                'won' => $won,
                'ip' => $ip,
                'device_fingerprint' => $deviceFingerprint,
            ]);

            if ($won && $amount > 0) {
                // Promotions multiply the base segment prize. With no
                // active promotion the amount passes through untouched.
                $boost = $this->promotions->applyMultipliers($amount, Promotion::SCOPE_SPIN);
                $finalAmount = $boost['coins'];

                $meta = ['spin_id' => $history->id, 'segment_index' => $segmentIndex];
                if ($boost['promotions']->isNotEmpty()) {
                    $meta['promotion_ids'] = $this->promotions->promotionIds($boost['promotions']);
                    $meta['base_amount'] = $amount;
                }

                if ($deferCredit) {
                    // Mobile gate: no credit yet — the claim is fulfilled by
                    // SpinClaimService::claim() after ad verification.
                    $claim = SpinClaim::create([
                        'user_id' => $user->id,
                        'spin_history_id' => $history->id,
                        'amount' => $finalAmount,
                        'token' => bin2hex(random_bytes(32)),
                        'expires_at' => now()->addMinutes($this->claims->claimExpiryMinutes()),
                    ]);
                    $claimToken = $claim->token;
                    $claimExpiresAt = $claim->expires_at->toIso8601String();
                } else {
                    $this->coins->credit(
                        $user,
                        $finalAmount,
                        CoinTransaction::SOURCE_SPIN,
                        "spin:{$history->id}",
                        null,
                        $meta
                    );
                }

                $amount = $finalAmount;
            }

            return [
                'history' => $history,
                'won' => $won,
                'amount' => $amount,
                'segments' => $segments,
                'segment_index' => $segmentIndex,
                'spins_left' => $this->spinsLeftToday($user),
                'claim_token' => $claimToken,
                'claim_expires_at' => $claimExpiresAt,
            ];
        });
    }
}
