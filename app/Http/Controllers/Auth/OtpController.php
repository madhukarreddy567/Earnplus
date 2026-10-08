<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Services\OtpService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class OtpController extends Controller
{
    /**
     * Show the OTP entry form.
     */
    public function notice(): View
    {
        return view('auth.verify-otp');
    }

    /**
     * Verify the submitted 6-digit code.
     */
    public function verify(Request $request, OtpService $otp): RedirectResponse
    {
        $request->validate([
            'code' => ['required', 'string', 'size:6'],
        ]);

        $user = $request->user();

        if ($otp->verify($user, $request->input('code'))) {
            $user->forceFill(['mobile_verified_at' => now()])->save();

            return redirect()->route('dashboard')->with('status', 'Mobile number verified!');
        }

        return back()->withErrors(['code' => 'Invalid or expired code. Please try again.']);
    }

    /**
     * Re-send a fresh OTP code.
     */
    public function resend(Request $request, OtpService $otp): RedirectResponse
    {
        $otp->send($request->user());

        return back()->with('status', 'A new code was sent to your mobile number.');
    }
}
