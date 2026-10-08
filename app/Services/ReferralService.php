<?php

namespace App\Services;

use App\Models\CoinTransaction;
use App\Models\Promotion;
use App\Models\User;

/**
 * Referral bonuses. The referrer earns coins when someone registers
 * with their code. Self-referral is impossible by construction (the
 * code must belong to a different, already-existing user).
 */
class ReferralService
{
    public function __construct(
        protected CoinService $coins,
        protected PromotionService $promotions
    ) {
    }

    /**
     * Resolve a referral code to the referring user, or null when
     * referrals are disabled / the code is blank or unknown.
     */
    public function resolveReferrer(?string $code): ?User
    {
        $code = trim((string) $code);

        if ($code === '' || ! setting_bool('referral_enabled', true)) {
            return null;
        }

        return User::where('referral_code', strtoupper($code))->first();
    }

    /**
     * Attach a referrer to a newly registered user and credit the
     * referrer. Idempotent per new user — safe to call once.
     */
    public function rewardReferrer(User $newUser, User $referrer): CoinTransaction
    {
        if ($referrer->id === $newUser->id) {
            throw new \InvalidArgumentException('Self-referral is not allowed.');
        }

        $newUser->referred_by = $referrer->id;
        $newUser->save();

        $baseBonus = setting_int('referral_bonus_coins', 25);
        $boost = $this->promotions->applyMultipliers($baseBonus, Promotion::SCOPE_REFERRAL_BONUS);

        $meta = ['referred_user_id' => $newUser->id, 'referred_user_email' => $newUser->email];
        if ($boost['promotions']->isNotEmpty()) {
            $meta['promotion_ids'] = $this->promotions->promotionIds($boost['promotions']);
            $meta['base_amount'] = $baseBonus;
        }

        return $this->coins->credit(
            $referrer,
            $boost['coins'],
            CoinTransaction::SOURCE_REFERRAL_BONUS,
            "referral_bonus:{$newUser->id}",
            (string) $newUser->id,
            $meta
        );
    }
}
