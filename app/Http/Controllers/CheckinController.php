<?php

namespace App\Http\Controllers;

use App\Services\AlreadyCheckedInException;
use App\Services\CheckinService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class CheckinController extends Controller
{
    /**
     * Claim today's daily check-in coins.
     */
    public function store(Request $request, CheckinService $checkin): RedirectResponse
    {
        $user = $request->user();

        if ($checkin->checkedInToday($user)) {
            return redirect()->route('dashboard')
                ->with('status', 'You already checked in today — come back tomorrow!');
        }

        try {
            $result = $checkin->checkin($user);
        } catch (AlreadyCheckedInException $e) {
            return redirect()->route('dashboard')
                ->with('status', 'You already checked in today — come back tomorrow!');
        }

        $message = "Checked in! +{$result['amount']} coins (day {$result['streak']} streak).";
        if ($result['streak_bonus'] > 0) {
            $message .= " Streak bonus included! 🎉";
        }

        return redirect()->route('dashboard')->with('status', $message);
    }
}
