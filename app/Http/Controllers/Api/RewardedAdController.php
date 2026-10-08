<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AdPlacement;
use App\Services\RewardedAdService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Rewarded-ad claims for the mobile app.
 *
 * Native rewarded ads (AdMob/Unity SDKs) credit through the server-to-server
 * callbacks (/ads/verify/{network}) — this endpoint covers placements the
 * app shows in a WebView, with the same server-side checks as the web.
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
