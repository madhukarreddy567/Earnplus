<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\AlreadyCheckedInException;
use App\Services\CheckinService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Daily check-in for the mobile app (JSON mirror of the web flow).
 */
class CheckinController extends Controller
{
    public function status(Request $request, CheckinService $checkin): JsonResponse
    {
        $user = $request->user();

        return response()->json([
            'checked_in_today' => $checkin->checkedInToday($user),
            'streak' => $checkin->currentStreak($user),
        ]);
    }

    public function store(Request $request, CheckinService $checkin): JsonResponse
    {
        $user = $request->user();

        try {
            $result = $checkin->checkin($user);
        } catch (AlreadyCheckedInException) {
            return response()->json(
                ['message' => 'You already checked in today — come back tomorrow!'],
                409
            );
        }

        return response()->json([
            'amount' => $result['amount'],
            'streak' => $result['streak'],
            'streak_bonus' => $result['streak_bonus'],
            'balance' => $user->fresh()->coinBalance(),
        ]);
    }
}
