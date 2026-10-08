<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Referral stats for the mobile app's invite screen.
 */
class ReferralController extends Controller
{
    public function show(Request $request): JsonResponse
    {
        $user = $request->user();

        $referred = $user->referrals()->count();
        $bonus = setting_int('referral_bonus_coins', 25);

        return response()->json([
            'code' => $user->referral_code,
            'referred_count' => $referred,
            'bonus_coins' => $bonus,
            'share_text' => 'Join me on ' . setting('site_name', 'EarnPlus')
                . ' and earn coins! Use my code: ' . $user->referral_code,
        ]);
    }
}
