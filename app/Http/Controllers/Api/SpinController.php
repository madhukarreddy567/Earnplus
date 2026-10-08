<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\SpinClaimService;
use App\Services\SpinDisabledException;
use App\Services\SpinLimitReachedException;
use App\Services\SpinService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Spin wheel for the mobile app (JSON mirror of the web flow —
 * the outcome is decided server-side, the client only renders it).
 *
 * Reward gating: POST /api/spin mints a single-use claim token on a win
 * but does NOT credit. The app must show a Unity rewarded ad
 * (serverId "{userId}:spin:{token}") and then POST /api/spin/claim —
 * the wallet is credited only after Unity's signed S2S callback
 * verifies the ad view for that token.
 */
class SpinController extends Controller
{
    public function __construct(
        protected SpinClaimService $claims
    ) {
    }

    public function status(Request $request, SpinService $spin): JsonResponse
    {
        if (! $spin->enabled()) {
            return response()->json(['enabled' => false], 404);
        }

        return response()->json([
            'enabled' => true,
            'segments' => $spin->segments(),
            'spins_left' => $spin->spinsLeftToday($request->user()),
            'daily_limit' => $spin->dailyLimit(),
            'ad_gate_enabled' => $this->claims->gateEnabled(),
        ]);
    }

    public function spin(Request $request, SpinService $spin): JsonResponse
    {
        $request->validate([
            'device_fingerprint' => ['nullable', 'string', 'max:64'],
        ]);

        $user = $request->user();

        try {
            // deferCredit: a win mints a claim token — no credit yet.
            $result = $spin->spin(
                $user,
                $request->ip(),
                $request->input('device_fingerprint'),
                true
            );
        } catch (SpinDisabledException) {
            return response()->json(['message' => 'The spin wheel is currently disabled.'], 403);
        } catch (SpinLimitReachedException) {
            return response()->json(['message' => 'Daily spin limit reached. Try again tomorrow!'], 429);
        }

        return response()->json([
            'won' => $result['won'],
            'amount' => $result['amount'],
            'segment_index' => $result['segment_index'],
            'segments' => $result['segments'],
            'spins_left' => $result['spins_left'],
            'balance' => $user->fresh()->coinBalance(),
            'claim_token' => $result['claim_token'],
            'claim_expires_at' => $result['claim_expires_at'],
        ]);
    }

    /**
     * Claim a pending spin reward. Credits only when the claim token is
     * valid, unexpired, owned by the caller, and Unity's S2S callback has
     * verified the rewarded-ad view for it.
     */
    public function claim(Request $request): JsonResponse
    {
        $request->validate([
            'claim_token' => ['required', 'string', 'max:64'],
        ]);

        $result = $this->claims->claim($request->user(), $request->input('claim_token'));

        if ($result['http'] !== 200) {
            return response()->json(['message' => $result['message']], $result['http']);
        }

        return response()->json([
            'coins' => $result['coins'],
            'balance' => $result['balance'],
        ]);
    }

    /**
     * Pollable claim status — the app polls this after the ad closes
     * because Unity's S2S callback arrives asynchronously.
     */
    public function claimStatus(Request $request, string $token): JsonResponse
    {
        $status = $this->claims->statusFor($request->user(), $token);

        if ($status === null) {
            return response()->json(['message' => 'Claim not found.'], 404);
        }

        return response()->json($status);
    }
}
