<?php

namespace App\Services\Ads;

use App\Models\AdNetwork;
use App\Models\AdReward;
use App\Models\CoinTransaction;
use App\Models\Promotion;
use App\Models\User;
use App\Services\CoinService;
use App\Services\PromotionService;
use App\Services\SpinClaimService;
use Illuminate\Http\Request;

/**
 * Server-to-server rewarded-ad callbacks from mobile SDK networks
 * (AdMob SSV, Unity Ads S2S).
 *
 * These networks reward the user inside the native app and then call us
 * to confirm. We verify their cryptographic signature, dedupe by their
 * transaction id, enforce the same daily limit as web rewarded ads, and
 * credit through CoinService — idempotent, so retried callbacks can
 * never double-pay.
 */
class AdVerificationService
{
    public function __construct(
        protected CoinService $coins,
        protected PromotionService $promotions
    ) {
    }

    /**
     * @return array{status: string, http: int, coins?: int}
     */
    public function handle(AdNetwork $network, Request $request): array
    {
        if (! $network->enabled || ! $network->isMobileSdk()) {
            return ['status' => 'network_disabled', 'http' => 403];
        }

        $verifier = match ($network->type) {
            AdNetwork::TYPE_ADMOB => app(AdmobSsvVerifier::class),
            AdNetwork::TYPE_UNITY_ADS => app(UnityS2sVerifier::class),
            default => null,
        };

        if ($verifier === null) {
            return ['status' => 'unsupported_network', 'http' => 400];
        }

        $verified = $verifier->verify($network, $request);

        if ($verified === null) {
            return ['status' => 'invalid_signature', 'http' => 401];
        }

        // Spin-reward gate: the app passes serverId "{userId}:spin:{token}".
        // A verified callback here marks the claim ad-verified — it does NOT
        // credit (the credit happens at POST /api/spin/claim) and does NOT
        // consume the standalone rewarded-ad daily limit.
        if (
            $network->type === AdNetwork::TYPE_UNITY_ADS
            && isset($verified['raw_oid'])
            && SpinClaimService::matchesOid($verified['raw_oid'])
        ) {
            $claim = app(SpinClaimService::class)->recordUnityCompletion(
                $verified['raw_oid'],
                $verified['transaction_id']
            );

            return $claim
                ? ['status' => 'spin_claim_verified', 'http' => 200]
                : ['status' => 'spin_claim_not_found', 'http' => 200];
        }

        $user = User::find($verified['user_id']);

        if (! $user) {
            return ['status' => 'unknown_user', 'http' => 200];
        }

        // Same daily cap as web rewarded ads — verified callbacks are
        // trustworthy, this is just belt-and-suspenders.
        $dailyLimit = setting_int('rewarded_ad_daily_limit', 10);
        $todayCount = AdReward::where('user_id', $user->id)
            ->whereDate('created_at', today())
            ->count();

        if ($todayCount >= $dailyLimit) {
            return ['status' => 'daily_limit_reached', 'http' => 200];
        }

        // Promotions multiply the verified coin amount. Untouched when
        // nothing is active.
        $boost = $this->promotions->applyMultipliers($verified['coins'], Promotion::SCOPE_REWARDED_AD);
        $finalCoins = $boost['coins'];

        $meta = ['network' => $network->slug, 's2s' => true];
        if ($boost['promotions']->isNotEmpty()) {
            $meta['promotion_ids'] = $this->promotions->promotionIds($boost['promotions']);
            $meta['base_amount'] = $verified['coins'];
        }

        $tx = $this->coins->credit(
            $user,
            $finalCoins,
            CoinTransaction::SOURCE_REWARDED_AD,
            "s2s:{$network->slug}:{$verified['transaction_id']}",
            (string) $verified['transaction_id'],
            $meta
        );

        // Keep the reward log consistent with web claims.
        AdReward::firstOrCreate(
            ['idempotency_key' => "s2s:{$network->slug}:{$verified['transaction_id']}"],
            [
                'user_id' => $user->id,
                'placement_id' => null,
                'coins' => $finalCoins,
            ]
        );

        return ['status' => 'credited', 'http' => 200, 'coins' => $tx->amount];
    }
}
