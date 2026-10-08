<?php

namespace App\Services\Ads;

use App\Models\AdNetwork;
use Illuminate\Http\Request;

/**
 * Unity Ads server-to-server (install/reward) callback verifier.
 *
 * Unity calls our callback with GET parameters including `sid`
 * (their transaction id), `oid` (our user id, echoed back from the
 * `custom_data` we passed the SDK), and `hmac` — an HMAC-SHA256 of the
 * alphabetically-sorted query string (excluding `hmac` itself) keyed
 * with the S2S secret from the Unity dashboard (stored in the
 * network's config as `s2s_secret`).
 */
class UnityS2sVerifier implements S2sVerifier
{
    public function verify(AdNetwork $network, Request $request): ?array
    {
        $secret = (string) ($network->config['s2s_secret'] ?? '');

        if ($secret === '') {
            return null;
        }

        $params = $request->query->all();
        $hmac = (string) ($params['hmac'] ?? '');
        unset($params['hmac']);

        if ($hmac === '') {
            return null;
        }

        ksort($params);
        $expected = hash_hmac('sha256', http_build_query($params), $secret);

        if (! hash_equals($expected, $hmac)) {
            return null;
        }

        $userId = (int) ($params['oid'] ?? 0);
        $transactionId = (string) ($params['sid'] ?? '');

        if ($userId <= 0 || $transactionId === '') {
            return null;
        }

        return [
            'user_id' => $userId,
            'transaction_id' => $transactionId,
            'coins' => max(1, (int) ($params['reward_amount'] ?? 0)),
            // The untouched oid, so callers can detect special shapes like
            // spin-claim serverIds ("{userId}:spin:{claimToken}").
            'raw_oid' => (string) ($params['oid'] ?? ''),
        ];
    }
}
