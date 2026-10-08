<?php

namespace App\Http\Controllers;

use App\Models\OfferwallConversion;
use App\Models\OfferwallProvider;
use App\Services\PostbackService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * Signed postback endpoint for offerwall providers.
 *
 * POST /postback/{provider:slug}  (also accepts GET — every major
 * offerwall network fires GET postbacks with query parameters)
 *
 * No session auth — trust comes from the per-provider signature scheme
 * (see provider config key 'signature'):
 *   - 'hmac_raw_body' (default): HMAC-SHA256 over the raw body with the
 *     provider's postback_secret, via X-Signature header or ?sig= fallback.
 *   - 'none': no signature documented by the network (e.g. AdGate);
 *     IP whitelist + click validation + dedupe still apply.
 *   - 'adgem_v2': AdGem's v2 hashing — 'verifier' param must equal
 *     HMAC-SHA256 over the alphabetically-sorted query string
 *     (excluding 'verifier' and 'request_id'), keyed with postback_secret.
 *   - 'timewall_sha256': TimeWall's {hash} macro — SHA256 hex of
 *     (userID . revenue . SecretKey) concatenated with NO separator,
 *     using the RAW query-string values (revenue exactly as received,
 *     e.g. "0.002" — never rounded or reformatted). The Secret Key is
 *     the provider's postback_secret (pasted from the TimeWall
 *     dashboard). Coins credited = the `currency` (currencyAmount)
 *     param when sane, else revenue x the configured conversion rate;
 *     non-earning `type` values (withdrawals etc.) are logged for
 *     review and never credited.
 *
 * The optional IP whitelist is always enforced when configured.
 * Provider-specific parameter names are normalized through the
 * provider config's 'param_map' (their name => canonical key:
 * provider_tx_id, user_id, payout, click_uid).
 *
 * Every outcome returns 200 + {status} so providers don't retry
 * settled postbacks; only auth failures use 401/403/404.
 */
class PostbackController extends Controller
{
    public function __construct(protected PostbackService $postbacks)
    {
    }

    public function handle(Request $request, OfferwallProvider $provider): JsonResponse
    {
        if (! $provider->enabled) {
            return response()->json(['status' => 'provider_disabled'], 403);
        }

        $config = $provider->config ?? [];
        $isTimeWall = ($config['signature'] ?? '') === 'timewall_sha256';

        // Signature first, from header or ?sig= fallback (transport only —
        // never part of the business payload).
        $signature = $request->header('X-Signature') ?? $request->query('sig');

        // Payload: JSON body wins, query params fill the gaps (GET postbacks).
        $payload = $request->json()->all();
        if (! is_array($payload)) {
            $payload = [];
        }
        foreach ($request->query() as $key => $value) {
            if ($key !== 'sig' && ! array_key_exists($key, $payload)) {
                $payload[$key] = $value;
            }
        }

        // Normalize provider-specific parameter names to canonical keys.
        $payload = $this->applyParamMap($config['param_map'] ?? [], $payload);

        // Chargebacks (e.g. AdGate status=0): never credit — flag for review.
        if ($this->isChargeback($config, $payload)) {
            $result = $this->postbacks->recordChargeback(
                $provider,
                (string) ($payload['provider_tx_id'] ?? ''),
                $payload
            );

            return response()->json($result);
        }

        if (! $this->signatureValid($provider, $config, $request, $signature)) {
            return response()->json(['status' => 'invalid_signature'], 401);
        }

        if (! $provider->ipAllowed($request->ip())) {
            Log::warning('Postback rejected: IP not whitelisted', [
                'provider' => $provider->slug,
                'ip' => $request->ip(),
            ]);

            return response()->json(['status' => 'ip_blocked'], 403);
        }

        if ($isTimeWall) {
            // TimeWall non-earning event types (e.g. withdrawals): log for
            // admin review, never credit.
            if ($this->isTimeWallNonEarning($config, $payload)) {
                $result = $this->postbacks->recordChargeback(
                    $provider,
                    (string) ($payload['provider_tx_id'] ?? ''),
                    $payload
                );

                return response()->json($result);
            }

            // Translate TimeWall's currency/revenue params into the
            // canonical coin payout the ledger expects.
            $payload = $this->normalizeTimeWallPayload($config, $payload);
        }

        $result = $this->postbacks->handle(
            $provider,
            $payload,
            $request->ip(),
            $request->userAgent()
        );

        // Duplicate acknowledgements carry no new information beyond the id.
        if ($result['status'] === OfferwallConversion::STATUS_DUPLICATE) {
            return response()->json(['status' => 'duplicate']);
        }

        return response()->json($result);
    }

    /**
     * Map provider-specific parameter names to canonical payload keys.
     * Unmapped keys pass through untouched.
     *
     * @param array<string,string> $map  their name => canonical key
     */
    protected function applyParamMap(array $map, array $payload): array
    {
        if ($map === []) {
            return $payload;
        }

        $mapped = [];
        foreach ($payload as $key => $value) {
            $mapped[$map[$key] ?? $key] = $value;
        }

        return $mapped;
    }

    protected function isChargeback(array $config, array $payload): bool
    {
        $param = $config['chargeback_param'] ?? null;
        $values = $config['chargeback_values'] ?? [];

        if (! is_string($param) || $param === '' || $values === []) {
            return false;
        }

        return isset($payload[$param])
            && in_array((string) $payload[$param], array_map('strval', $values), true);
    }

    protected function signatureValid(
        OfferwallProvider $provider,
        array $config,
        Request $request,
        ?string $signature
    ): bool {
        $mode = $config['signature'] ?? 'hmac_raw_body';

        if ($mode === 'none') {
            // Network documents no signature (e.g. AdGate): IP whitelist,
            // click validation and dedupe remain the trust layers.
            return true;
        }

        if ($mode === 'adgem_v2') {
            return $this->verifyAdGemV2($provider, $request);
        }

        if ($mode === 'timewall_sha256') {
            return $this->verifyTimeWall($provider, $request);
        }

        // Default: HMAC-SHA256 over the raw body.
        return $this->postbacks->verifySignature($provider, $request->getContent(), $signature);
    }

    /**
     * AdGem v2 server-postback hashing: AdGem appends 'request_id' and
     * 'verifier' to the GET postback. The verifier must equal HMAC-SHA256
     * over the alphabetically-sorted query string (excluding 'verifier'
     * and 'request_id'), keyed with the provider's postback secret
     * (the one-time Postback Key from the AdGem dashboard).
     *
     * NOTE: confirm this exact computation against the AdGem publisher
     * docs (Postback Settings) before enabling the provider live.
     */
    protected function verifyAdGemV2(OfferwallProvider $provider, Request $request): bool
    {
        $query = $request->query();
        $verifier = $query['verifier'] ?? null;

        if (! is_string($verifier) || $verifier === '') {
            return false;
        }

        unset($query['verifier'], $query['request_id'], $query['sig']);
        ksort($query);

        $canonical = implode('&', array_map(
            fn ($k, $v) => $k . '=' . $v,
            array_keys($query),
            array_values($query)
        ));

        $expected = hash_hmac('sha256', $canonical, (string) $provider->postback_secret);

        return hash_equals($expected, strtolower(trim($verifier)));
    }

    /**
     * TimeWall's {hash} macro: SHA256 hex of (userID . revenue . SecretKey)
     * concatenated with NO separator.
     *
     * CRITICAL: userID and revenue must be the RAW query-string values —
     * the revenue string exactly as TimeWall sent it (e.g. "0.002", not
     * "0.00"). We parse them out of the raw QUERY_STRING instead of the
     * normalized request bag so no float cast or reformatting can sneak
     * in ("0.50" hashes differently from "0.5").
     */
    protected function verifyTimeWall(OfferwallProvider $provider, Request $request): bool
    {
        $queryString = (string) $request->server->get('QUERY_STRING', '');

        $userId = $this->rawQueryParam($queryString, 'userid');
        $revenue = $this->rawQueryParam($queryString, 'revenue');
        $hash = $this->rawQueryParam($queryString, 'hash');

        if ($userId === null || $revenue === null || $hash === null || $hash === '') {
            return false;
        }

        $expected = hash('sha256', $userId . $revenue . (string) $provider->postback_secret);

        return hash_equals($expected, strtolower(trim($hash)));
    }

    /**
     * Extract one parameter's raw (still URL-encoded) value from a query
     * string, then decode it once. Returns null when absent.
     */
    protected function rawQueryParam(string $queryString, string $name): ?string
    {
        if (preg_match('/(?:^|&)' . preg_quote($name, '/') . '=([^&]*)/', $queryString, $matches)) {
            return urldecode($matches[1]);
        }

        return null;
    }

    /**
     * Conservative guard for TimeWall's `type` parameter: event types that
     * look like withdrawals/reversals must never credit. TimeWall does not
     * document the full list of type values, so this is a substring
     * denylist (case-insensitive) — everything else flows through the
     * normal signed + IP-checked + click-validated credit pipeline.
     */
    protected function isTimeWallNonEarning(array $config, array $payload): bool
    {
        $type = strtolower((string) ($payload['tw_type'] ?? ''));

        if ($type === '') {
            return false;
        }

        $hints = $config['non_earning_type_hints']
            ?? ['withdraw', 'reversal', 'chargeback', 'refund', 'cancel'];

        foreach ($hints as $hint) {
            if (str_contains($type, strtolower((string) $hint))) {
                return true;
            }
        }

        return false;
    }

    /**
     * Translate TimeWall's money params into the canonical integer coin
     * payout the ledger expects. `currency` (their currencyAmount) is used
     * when present and sane; otherwise revenue_usd x the configured
     * conversion rate (matches the placement's Currency Conversion Rate).
     * Anything unusable becomes 0, which the ledger rejects as
     * invalid_payload — never credited.
     */
    protected function normalizeTimeWallPayload(array $config, array $payload): array
    {
        $coins = null;

        $currency = $payload['coins'] ?? null;
        if (is_numeric($currency) && (float) $currency > 0 && (float) $currency <= 1000000) {
            $coins = (int) round((float) $currency);
        } else {
            $revenue = $payload['revenue_usd'] ?? null;
            $rate = (float) ($config['timewall_currency_rate'] ?? 5000);
            if (is_numeric($revenue) && (float) $revenue > 0 && $rate > 0) {
                $coins = (int) round((float) $revenue * $rate);
            }
        }

        $payload['payout'] = $coins ?? 0;

        return $payload;
    }
}
