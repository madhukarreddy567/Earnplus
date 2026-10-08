<?php

namespace App\Http\Controllers;

use App\Models\AdPlacement;
use App\Services\RewardedAdService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * POST /ads/reward/{placement:slug}
 *
 * Claims a rewarded-ad payout. The front-end countdown is cosmetic —
 * RewardedAdService re-checks everything server-side (placement type and
 * enabled state, daily limit, minimum interval, velocity, shared-device).
 */
class RewardedAdController extends Controller
{
    public function __construct(protected RewardedAdService $rewarded)
    {
    }

    public function claim(Request $request, AdPlacement $placement): JsonResponse
    {
        $result = $this->rewarded->claim($placement, $request->user(), $request);

        if ($result['status'] !== 'ok') {
            return response()->json(
                ['message' => $result['message']],
                $result['http']
            );
        }

        return response()->json([
            'coins' => $result['coins'],
            'balance' => $result['balance'],
        ]);
    }
}
