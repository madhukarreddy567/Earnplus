<?php

namespace App\Services;

use App\Models\CoinTransaction;
use App\Models\SpinClaim;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Server-side gate for spin rewards: "watch a Unity rewarded ad to claim".
 *
 * Flow:
 *   1. POST /api/spin decides the outcome and mints a single-use,
 *      expiring SpinClaim (no credit yet).
 *   2. The app shows a Unity rewarded ad with serverId
 *      "{userId}:spin:{claimToken}".
 *   3. Unity's SIGNED server-to-server callback hits /ads/verify/unity-ads;
 *      AdVerificationService routes spin-claim oids here via
 *      recordUnityCompletion() — the ONLY thing that marks a claim
 *      ad-verified. Client assertions are never trusted.
 *   4. POST /api/spin/claim credits the wallet — only when the claim is
 *      pending, unexpired, owned by the caller, and ad-verified
 *      (unless the admin disabled the gate for development).
 *
 * Every claim attempt is logged and counted on the claim row.
 */
class SpinClaimService
{
    /**
     * The serverId shape the app sends Unity for spin claims:
     * "{userId}:spin:{64-hex claim token}".
     */
    public const OID_PATTERN = '/^(\d+):spin:([0-9a-f]{64})$/';

    public function __construct(
        protected CoinService $coins
    ) {
    }

    public function gateEnabled(): bool
    {
        return setting_bool('spin_ad_gate_enabled', true);
    }

    public function claimExpiryMinutes(): int
    {
        return max(1, setting_int('spin_claim_expiry_minutes', 15));
    }

    public static function matchesOid(string $rawOid): bool
    {
        return (bool) preg_match(self::OID_PATTERN, $rawOid);
    }

    /**
     * Record a verified Unity S2S completion against a pending claim.
     * Called ONLY from the signed-callback path — never from the app.
     */
    public function recordUnityCompletion(string $rawOid, string $sid): ?SpinClaim
    {
        if (! preg_match(self::OID_PATTERN, $rawOid, $m)) {
            return null;
        }

        $claim = SpinClaim::where('token', $m[2])
            ->where('user_id', (int) $m[1])
            ->first();

        if (! $claim) {
            Log::warning('spin_claim.unity_completion_unknown_token', [
                'user_id' => (int) $m[1],
                'sid' => $sid,
            ]);

            return null;
        }

        if ($claim->isClaimed()) {
            return $claim;
        }

        if ($claim->status === SpinClaim::STATUS_EXPIRED || $claim->isExpired()) {
            $claim->update(['status' => SpinClaim::STATUS_EXPIRED]);

            return null;
        }

        $claim->update([
            'status' => SpinClaim::STATUS_VERIFIED,
            'unity_sid' => $sid,
            'unity_verified_at' => now(),
        ]);

        Log::info('spin_claim.ad_verified', [
            'claim_id' => $claim->id,
            'user_id' => $claim->user_id,
            'sid' => $sid,
        ]);

        return $claim;
    }

    /**
     * Attempt to claim a pending spin reward.
     *
     * @return array{status: string, http: int, message?: string, coins?: int, balance?: int}
     */
    public function claim(User $user, string $token): array
    {
        $claim = SpinClaim::where('token', $token)
            ->where('user_id', $user->id)
            ->first();

        if (! $claim) {
            Log::info('spin_claim.attempt', [
                'user_id' => $user->id,
                'outcome' => 'not_found',
            ]);

            return ['status' => 'not_found', 'http' => 404, 'message' => 'Claim not found.'];
        }

        $claim->increment('attempts');
        $claim->update(['last_attempt_at' => now()]);

        $outcome = null;
        $result = null;

        if ($claim->isClaimed()) {
            $outcome = 'already_claimed';
            $result = ['status' => 'already_claimed', 'http' => 422, 'message' => 'This reward was already claimed.'];
        } elseif ($claim->status === SpinClaim::STATUS_EXPIRED || $claim->isExpired()) {
            $claim->update(['status' => SpinClaim::STATUS_EXPIRED]);
            $outcome = 'expired';
            $result = ['status' => 'expired', 'http' => 422, 'message' => 'This reward claim has expired. Spin again!'];
        } elseif ($this->gateEnabled() && ! $claim->isAdVerified()) {
            $outcome = 'ad_not_verified';
            $result = ['status' => 'ad_not_verified', 'http' => 422, 'message' => 'Watch the reward video to claim your coins.'];
        }

        if ($result !== null) {
            Log::info('spin_claim.attempt', [
                'claim_id' => $claim->id,
                'user_id' => $user->id,
                'outcome' => $outcome,
            ]);

            return $result;
        }

        // Verified (or gate disabled for development) — credit exactly once.
        // The idempotency key makes even a raced double-submit safe.
        $credited = DB::transaction(function () use ($claim, $user) {
            $tx = $this->coins->credit(
                $user,
                $claim->amount,
                CoinTransaction::SOURCE_SPIN,
                "spin-claim:{$claim->token}",
                null,
                ['spin_id' => $claim->spin_history_id, 'ad_gated' => true]
            );

            $claim->update([
                'status' => SpinClaim::STATUS_CLAIMED,
                'claimed_at' => now(),
            ]);

            return $tx;
        });

        Log::info('spin_claim.attempt', [
            'claim_id' => $claim->id,
            'user_id' => $user->id,
            'outcome' => 'credited',
            'coins' => $credited->amount,
        ]);

        return [
            'status' => 'credited',
            'http' => 200,
            'coins' => $credited->amount,
            'balance' => $user->fresh()->coinBalance(),
        ];
    }

    /**
     * Pollable status for a claim (the app polls this after the ad closes
     * because Unity's S2S callback arrives asynchronously).
     *
     * @return array{status: string, ad_verified: bool, expired: bool, claimed: bool, amount: int, expires_at: string}|null
     */
    public function statusFor(User $user, string $token): ?array
    {
        $claim = SpinClaim::where('token', $token)
            ->where('user_id', $user->id)
            ->first();

        if (! $claim) {
            return null;
        }

        if (! $claim->isClaimed()
            && $claim->status !== SpinClaim::STATUS_EXPIRED
            && $claim->isExpired()
        ) {
            $claim->update(['status' => SpinClaim::STATUS_EXPIRED]);
        }

        return [
            'status' => $claim->status,
            'ad_verified' => $claim->isAdVerified(),
            'expired' => $claim->status === SpinClaim::STATUS_EXPIRED,
            'claimed' => $claim->isClaimed(),
            'amount' => $claim->amount,
            'expires_at' => $claim->expires_at->toIso8601String(),
        ];
    }
}
