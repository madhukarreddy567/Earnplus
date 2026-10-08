<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\CoinTransaction;
use App\Services\CoinService;
use Illuminate\Foundation\Auth\EmailVerificationRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class EmailVerificationController extends Controller
{
    /**
     * Show the "verify your e-mail" notice.
     */
    public function notice(): View
    {
        return view('auth.verify-email');
    }

    /**
     * Mark the e-mail address as verified (signed link).
     * The signup bonus is credited here — idempotent, so re-visiting
     * the link can never double-credit.
     */
    public function verify(EmailVerificationRequest $request, CoinService $coins): RedirectResponse
    {
        if ($request->user()->hasVerifiedEmail()) {
            return redirect()->route('dashboard');
        }

        $request->fulfill();

        $user = $request->user();
        $bonus = setting_int('signup_bonus_coins', 50);

        if ($bonus > 0) {
            $coins->credit(
                $user,
                $bonus,
                CoinTransaction::SOURCE_SIGNUP_BONUS,
                "signup_bonus:{$user->id}"
            );
        }

        return redirect()->route('dashboard')->with('status', 'E-mail verified! Welcome to EarnPlus.');
    }

    /**
     * Re-send the verification e-mail.
     */
    public function resend(Request $request): RedirectResponse
    {
        if ($request->user()->hasVerifiedEmail()) {
            return redirect()->route('dashboard');
        }

        $request->user()->sendEmailVerificationNotification();

        return back()->with('status', 'Verification link sent!');
    }
}
