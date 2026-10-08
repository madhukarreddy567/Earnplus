<?php

namespace App\Services;

use App\Models\CoinTransaction;
use App\Models\OfferwallClick;
use App\Models\OfferwallConversion;
use App\Models\OfferwallProvider;
use App\Models\Promotion;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Handles incoming provider postbacks: signature + IP verification,
 * duplicate detection, click validation and coin crediting.
 *
 * Every outcome (credited / duplicate / rejected) returns HTTP 200 with a
 * JSON {status} body — providers retry on non-2xx, so only real server
 * errors use 4xx/5xx.
 */
class PostbackService
{
    public function __construct(
        protected CoinService $coins,
        protected OfferwallService $offerwalls,
        protected PromotionService $promotions
    ) {
    }

    /**
     * Verify the HMAC-SHA256 signature over the raw request body.
     * Accepts the X-Signature header, with a ?sig= query fallback.
     */
    public function verifySignature(OfferwallProvider $provider, string $rawBody, ?string $signature): bool
    {
        if ($signature === null || $signature === '') {
            return false;
        }

        $expected = hash_hmac('sha256', $rawBody, $provider->postback_secret);

        return hash_equals($expected, strtolower(trim($signature)));
    }

    /**
     * Process one postback. Returns ['status' => credited|duplicate|rejected, ...].
     */
    public function handle(
        OfferwallProvider $provider,
        array $payload,
        ?string $clickIp = null,
        ?string $userAgent = null
    ): array {
        return DB::transaction(function () use ($provider, $payload, $clickIp, $userAgent) {
            $txId = (string) ($payload['provider_tx_id'] ?? '');
            $userId = $payload['user_id'] ?? null;
            $payout = (int) ($payload['payout'] ?? 0);
            $clickUid = isset($payload['click_uid']) ? (string) $payload['click_uid'] : null;

            $user = $userId !== null ? User::find($userId) : null;

            if ($txId === '' || $user === null || $payout < 1) {
                return $this->record($provider, $user, $txId, $clickUid, $payout, 0, OfferwallConversion::STATUS_REJECTED, $payload, [
                    'reason' => 'invalid_payload',
                ]);
            }

            // Duplicate? Unique (provider_id, provider_tx_id) makes
            // double-credit impossible; the repeat is acknowledged, not paid.
            $duplicate = OfferwallConversion::where('provider_id', $provider->id)
                ->where('provider_tx_id', $txId)
                ->lockForUpdate()
                ->first();

            if ($duplicate !== null) {
                return ['status' => 'duplicate', 'conversion_id' => $duplicate->id];
            }

            // A live click must exist: no click (or an expired/consumed one)
            // means the conversion did not come from our funnel.
            $click = $this->offerwalls->resolveClick($provider, $user, $clickUid);

            if ($click === null || ! $click->isActive()) {
                return $this->record($provider, $user, $txId, $clickUid, $payout, 0, OfferwallConversion::STATUS_REJECTED, $payload, [
                    'reason' => $click === null ? 'no_matching_click' : 'click_not_active',
                ]);
            }

            $userCoins = $provider->userCoinsFor($payout);
            $flags = $this->offerwalls->fraudFlags($click);

            $idempotencyKey = "offerwall:{$provider->slug}:{$txId}";

            // Promotions multiply the user's share. Untouched when none active.
            $boost = $this->promotions->applyMultipliers(
                $userCoins,
                Promotion::SCOPE_TASK_OFFERWALL,
                $provider->id
            );
            $finalCoins = $boost['coins'];

            $creditMeta = [
                'provider' => $provider->slug,
                'provider_tx_id' => $txId,
                'payout_coins' => $payout,
                'revenue_share' => $provider->user_revenue_share,
            ];
            if ($boost['promotions']->isNotEmpty()) {
                $creditMeta['promotion_ids'] = $this->promotions->promotionIds($boost['promotions']);
                $creditMeta['base_amount'] = $userCoins;
            }

            $transaction = $this->coins->credit(
                $user,
                $finalCoins,
                CoinTransaction::SOURCE_TASK_OFFERWALL,
                $idempotencyKey,
                "offerwall:{$provider->slug}:{$txId}",
                $creditMeta
            );

            $click->update(['status' => OfferwallClick::STATUS_CONVERTED]);

            $conversion = OfferwallConversion::create([
                'provider_id' => $provider->id,
                'user_id' => $user->id,
                'provider_tx_id' => $txId,
                'click_uid' => $click->click_uid,
                'payout_coins' => $payout,
                'user_coins' => $finalCoins,
                'status' => OfferwallConversion::STATUS_CREDITED,
                'raw_payload' => $payload,
                'meta' => array_filter([
                    'coin_transaction_id' => $transaction->id,
                    'click_id' => $click->id,
                    'click_ip' => $clickIp,
                    'fraud_flags' => $flags === [] ? null : $flags,
                ]),
                'credited_at' => now(),
            ]);

            return ['status' => 'credited', 'conversion_id' => $conversion->id, 'user_coins' => $finalCoins];
        });
    }

    /**
     * Manually credit a pending conversion from the admin panel.
     * Idempotent: only pending conversions move, and the CoinService
     * idempotency key prevents double payment.
     */
    public function creditManually(OfferwallConversion $conversion): array
    {
        return DB::transaction(function () use ($conversion) {
            $conversion = OfferwallConversion::whereKey($conversion->id)->lockForUpdate()->first();

            if (! $conversion->isPending()) {
                return ['status' => $conversion->status, 'conversion_id' => $conversion->id];
            }

            $provider = $conversion->provider;
            $user = $conversion->user;

            $boost = $this->promotions->applyMultipliers(
                $conversion->user_coins,
                Promotion::SCOPE_TASK_OFFERWALL,
                $provider->id
            );

            $creditMeta = [
                'provider' => $provider->slug,
                'provider_tx_id' => $conversion->provider_tx_id,
                'manual_credit' => true,
            ];
            if ($boost['promotions']->isNotEmpty()) {
                $creditMeta['promotion_ids'] = $this->promotions->promotionIds($boost['promotions']);
                $creditMeta['base_amount'] = $conversion->user_coins;
            }

            $transaction = $this->coins->credit(
                $user,
                $boost['coins'],
                CoinTransaction::SOURCE_TASK_OFFERWALL,
                "offerwall:{$provider->slug}:{$conversion->provider_tx_id}",
                "offerwall:{$provider->slug}:{$conversion->provider_tx_id}",
                $creditMeta
            );

            $conversion->update([
                'status' => OfferwallConversion::STATUS_CREDITED,
                'credited_at' => now(),
                'meta' => array_merge($conversion->meta ?? [], [
                    'coin_transaction_id' => $transaction->id,
                    'manual_credit' => true,
                ]),
            ]);

            return ['status' => 'credited', 'conversion_id' => $conversion->id];
        });
    }

    /**
     * Manually reject a pending conversion. Idempotent.
     */
    public function rejectManually(OfferwallConversion $conversion, ?string $reason = null): array
    {
        return DB::transaction(function () use ($conversion, $reason) {
            $conversion = OfferwallConversion::whereKey($conversion->id)->lockForUpdate()->first();

            if (! $conversion->isPending()) {
                return ['status' => $conversion->status, 'conversion_id' => $conversion->id];
            }

            $conversion->update([
                'status' => OfferwallConversion::STATUS_REJECTED,
                'meta' => array_merge($conversion->meta ?? [], array_filter([
                    'manual_reject' => true,
                    'reject_reason' => $reason,
                ])),
            ]);

            return ['status' => 'rejected', 'conversion_id' => $conversion->id];
        });
    }

    /**
     * Log a provider chargeback/reversal (e.g. AdGate status=0).
     * Never credits or debits — it flags the original conversion for
     * admin review (or logs a standalone rejected row when the original
     * is unknown). Always safe to call; duplicates are idempotent.
     */
    public function recordChargeback(
        OfferwallProvider $provider,
        string $txId,
        array $payload
    ): array {
        return DB::transaction(function () use ($provider, $txId, $payload) {
            $existing = $txId !== ''
                ? OfferwallConversion::where('provider_id', $provider->id)
                    ->where('provider_tx_id', $txId)
                    ->lockForUpdate()
                    ->first()
                : null;

            if ($existing !== null) {
                $existing->update([
                    'meta' => array_merge($existing->meta ?? [], [
                        'chargeback' => true,
                        'chargeback_at' => now()->toIso8601String(),
                        'chargeback_payload' => $payload,
                    ]),
                ]);

                return ['status' => 'chargeback', 'conversion_id' => $existing->id];
            }

            $conversion = OfferwallConversion::create([
                'provider_id' => $provider->id,
                'user_id' => null,
                'provider_tx_id' => $txId !== '' ? $txId . ':chargeback' : 'chargeback-' . uniqid(),
                'payout_coins' => 0,
                'user_coins' => 0,
                'status' => OfferwallConversion::STATUS_REJECTED,
                'raw_payload' => $payload,
                'meta' => ['reason' => 'chargeback', 'chargeback_at' => now()->toIso8601String()],
            ]);

            return ['status' => 'chargeback', 'conversion_id' => $conversion->id];
        });
    }

    /**
     * Record a rejected conversion (raw payload always logged).
     *
     * @return array{status: string, conversion_id: int}
     */
    protected function record(
        OfferwallProvider $provider,
        ?User $user,
        string $txId,
        ?string $clickUid,
        int $payout,
        int $userCoins,
        string $status,
        array $payload,
        array $meta
    ): array {
        $conversion = OfferwallConversion::create([
            'provider_id' => $provider->id,
            'user_id' => $user?->id,
            'provider_tx_id' => $txId !== '' ? $txId : 'invalid-' . uniqid(),
            'click_uid' => $clickUid,
            'payout_coins' => max(0, $payout),
            'user_coins' => $userCoins,
            'status' => $status,
            'raw_payload' => $payload,
            'meta' => $meta,
        ]);

        return ['status' => $status, 'conversion_id' => $conversion->id];
    }
}
