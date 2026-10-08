<?php

namespace App\Services;

use App\Models\CoinTransaction;
use App\Models\DailyCheckin;
use App\Models\Promotion;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

class AlreadyCheckedInException extends \RuntimeException
{
}

/**
 * Daily check-in: once per calendar day (app timezone), with a
 * consecutive-day streak. Every Nth streak day pays an extra bonus —
 * both the base amount and the streak settings come from the DB.
 */
class CheckinService
{
    public function __construct(
        protected CoinService $coins,
        protected PromotionService $promotions
    ) {
    }

    /**
     * Has this user already checked in today?
     */
    public function checkedInToday(User $user): bool
    {
        return DailyCheckin::where('user_id', $user->id)
            ->where('checked_in_on', $this->today())
            ->exists();
    }

    /**
     * The user's current streak (0 when never checked in or the
     * streak is broken).
     */
    public function currentStreak(User $user): int
    {
        $latest = DailyCheckin::where('user_id', $user->id)
            ->orderByDesc('checked_in_on')
            ->first();

        if ($latest === null) {
            return 0;
        }

        $today = $this->today();
        $checkedOn = CarbonImmutable::parse($latest->checked_in_on);

        // Streak is alive when the latest check-in was today or yesterday.
        if ($checkedOn->equalTo($today) || $checkedOn->equalTo($today->subDay())) {
            return $latest->streak;
        }

        return 0;
    }

    /**
     * Perform today's check-in and credit the coins.
     * Throws AlreadyCheckedInException when already done today.
     *
     * @return array{checkin: DailyCheckin, transaction: CoinTransaction, amount: int, streak: int, streak_bonus: int}
     */
    public function checkin(User $user): array
    {
        if (! setting_bool('daily_checkin_enabled', true)) {
            throw new \RuntimeException('Daily check-in is currently disabled.');
        }

        return DB::transaction(function () use ($user) {
            $today = $this->today();

            $exists = DailyCheckin::where('user_id', $user->id)
                ->where('checked_in_on', $today->toDateString())
                ->lockForUpdate()
                ->exists();

            if ($exists) {
                throw new AlreadyCheckedInException('Already checked in today.');
            }

            $yesterday = DailyCheckin::where('user_id', $user->id)
                ->where('checked_in_on', $today->subDay()->toDateString())
                ->first();

            $streak = $yesterday ? $yesterday->streak + 1 : 1;

            $checkin = DailyCheckin::create([
                'user_id' => $user->id,
                'checked_in_on' => $today->toDateString(),
                'streak' => $streak,
            ]);

            $amount = setting_int('daily_checkin_coins', 10);
            $streakBonus = 0;

            $every = setting_int('daily_checkin_streak_days', 7);
            if ($every > 0 && $streak % $every === 0) {
                $streakBonus = setting_int('daily_checkin_streak_bonus', 50);
            }

            $total = $amount + $streakBonus;

            // Promotions multiply the whole check-in payout (base +
            // streak bonus). Untouched when nothing is active.
            $boost = $this->promotions->applyMultipliers($total, Promotion::SCOPE_DAILY_CHECKIN);
            $finalTotal = $boost['coins'];

            $meta = ['streak' => $streak, 'base' => $amount, 'streak_bonus' => $streakBonus];
            if ($boost['promotions']->isNotEmpty()) {
                $meta['promotion_ids'] = $this->promotions->promotionIds($boost['promotions']);
            }

            $transaction = $this->coins->credit(
                $user,
                $finalTotal,
                CoinTransaction::SOURCE_DAILY_CHECKIN,
                "checkin:{$user->id}:{$today->toDateString()}",
                null,
                $meta
            );

            return [
                'checkin' => $checkin,
                'transaction' => $transaction,
                'amount' => $finalTotal,
                'streak' => $streak,
                'streak_bonus' => $streakBonus,
            ];
        });
    }

    protected function today(): CarbonImmutable
    {
        return CarbonImmutable::now(config('app.timezone'))->startOfDay();
    }
}
