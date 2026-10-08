<?php

namespace App\Http\Controllers;

use App\Services\SpinDisabledException;
use App\Services\SpinLimitReachedException;
use App\Services\SpinService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class SpinController extends Controller
{
    /**
     * The spin wheel page.
     */
    public function show(SpinService $spin): View
    {
        if (! $spin->enabled()) {
            abort(404);
        }

        return view('spin', [
            'segments' => $spin->segments(),
            'spinsLeft' => $spin->spinsLeftToday(auth()->user()),
            'dailyLimit' => $spin->dailyLimit(),
        ]);
    }

    /**
     * Perform one spin. The outcome is decided server-side — the
     * request body carries no prize information and is ignored
     * beyond the device fingerprint.
     */
    public function spin(Request $request, SpinService $spin): JsonResponse
    {
        $request->validate([
            'device_fingerprint' => ['nullable', 'string', 'max:64'],
        ]);

        $user = $request->user();

        try {
            $result = $spin->spin(
                $user,
                $request->ip(),
                $request->input('device_fingerprint')
            );
        } catch (SpinDisabledException $e) {
            return response()->json(['error' => 'The spin wheel is currently disabled.'], 403);
        } catch (SpinLimitReachedException $e) {
            return response()->json(['error' => 'Daily spin limit reached. Try again tomorrow!'], 429);
        }

        return response()->json([
            'won' => $result['won'],
            'amount' => $result['amount'],
            'segment_index' => $result['segment_index'],
            'segments' => $result['segments'],
            'spins_left' => $result['spins_left'],
            'balance' => $user->fresh()->coinBalance(),
        ]);
    }
}
