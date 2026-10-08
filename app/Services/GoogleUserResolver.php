<?php

namespace App\Services;

use App\Models\CoinTransaction;
use App\Models\User;
use Illuminate\Support\Str;

/**
 * Shared Google-user resolution for the web (Socialite) and mobile
 * (ID-token) sign-in flows. Both paths resolve to the same account:
 *
 * - Existing Google-linked user  → returned as-is.
 * - Same e-mail, no Google link  → linked to the existing row.
 * - Brand new                  → account created with the e-mail
 *   pre-verified (Google verified it), the standard signup bonus
 *   (idempotent), and the referral reward when a code is given.
 */
class GoogleUserResolver
{
    public function __construct(
        protected CoinService $coins,
        protected ReferralService $referrals
    ) {
    }

    public function resolve(
        string $googleId,
        ?string $email,
        ?string $name,
        ?string $avatar,
        ?string $referralCode = null
    ): User {
        $user = User::where('google_id', $googleId)->first();

        if (! $user && $email) {
            $user = User::where('email', $email)->first();
            if ($user) {
                $user->google_id = $googleId;
                if (empty($user->avatar) && $avatar) {
                    $user->avatar = $avatar;
                }
                $user->save();
            }
        }

        if (! $user) {
            $user = User::create([
                'name' => $name ?: explode('@', (string) $email)[0],
                'email' => $email,
                'google_id' => $googleId,
                'avatar' => $avatar,
                'password' => Str::random(40), // unusable placeholder; Google is the login
            ]);

            // Google verified this address — mark it directly (not via
            // mass assignment; email_verified_at is intentionally guarded).
            $user->email_verified_at = now();
            $user->save();

            // Signup bonus — the same idempotent key the e-mail flow uses.
            $bonus = setting_int('signup_bonus_coins', 50);
            if ($bonus > 0) {
                $this->coins->credit(
                    $user,
                    $bonus,
                    CoinTransaction::SOURCE_SIGNUP_BONUS,
                    "signup_bonus:{$user->id}"
                );
            }

            $referrer = $this->referrals->resolveReferrer($referralCode);
            if ($referrer !== null) {
                $this->referrals->rewardReferrer($user, $referrer);
            }
        }

        return $user;
    }
}
