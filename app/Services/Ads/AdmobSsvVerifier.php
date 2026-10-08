<?php

namespace App\Services\Ads;

use App\Models\AdNetwork;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

/**
 * Google AdMob Server-Side Verification (SSV).
 *
 * AdMob calls our callback with GET parameters including `signature`,
 * `key_id`, `transaction_id`, `user_id` (our custom_data echo), and
 * `reward_amount`. The signature is an RSA signature over the query
 * string, verifiable with Google's published public keys:
 * https://www.gstatic.com/admob/reward/verifier-keys.json
 *
 * Keys are cached for 24h; any fetch/parse/crypto failure is treated
 * as a failed verification (never as a pass).
 */
class AdmobSsvVerifier implements S2sVerifier
{
    public const KEYS_URL = 'https://www.gstatic.com/admob/reward/verifier-keys.json';

    public function verify(AdNetwork $network, Request $request): ?array
    {
        $params = $request->query->all();

        $signature = $params['signature'] ?? null;
        $keyId = $params['key_id'] ?? null;

        if (! is_string($signature) || ! is_string($keyId) || $signature === '' || $keyId === '') {
            return null;
        }

        $publicKey = $this->publicKey($keyId);

        if ($publicKey === null) {
            return null;
        }

        // Signed content: the full query string minus the signature param,
        // in the order AdMob sent it.
        unset($params['signature']);
        $signedContent = urldecode(http_build_query($params));

        $verified = openssl_verify(
            $signedContent,
            base64_decode($signature, true) ?: '',
            $publicKey,
            OPENSSL_ALGO_SHA256
        );

        if ($verified !== 1) {
            return null;
        }

        $userId = (int) ($params['user_id'] ?? 0);
        $transactionId = (string) ($params['transaction_id'] ?? '');

        if ($userId <= 0 || $transactionId === '') {
            return null;
        }

        return [
            'user_id' => $userId,
            'transaction_id' => $transactionId,
            'coins' => max(1, (int) ($params['reward_amount'] ?? 0)),
        ];
    }

    /**
     * Fetch (cached) the PEM public key for a key id.
     */
    protected function publicKey(string $keyId): ?string
    {
        try {
            $keys = cache()->remember('admob_ssv_keys', now()->addDay(), function () {
                $response = Http::timeout(10)->get(self::KEYS_URL);

                if (! $response->successful()) {
                    return [];
                }

                return $response->json('keys', []);
            });

            foreach ((array) $keys as $key) {
                if (($key['keyId'] ?? null) == $keyId) {
                    return $key['pem'] ?? null;
                }
            }
        } catch (\Throwable) {
            return null;
        }

        return null;
    }
}
