<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\OtpService;
use App\Services\RecaptchaService;
use App\Services\ReferralService;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class RegisterController extends Controller
{
    /**
     * Show the registration form.
     */
    public function show(Request $request): View
    {
        return view('auth.register', [
            'referralCode' => $request->query('ref', ''),
        ]);
    }

    /**
     * Handle a registration request.
     */
    public function store(Request $request, RecaptchaService $recaptcha, OtpService $otp, ReferralService $referrals): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users'],
            'mobile' => ['nullable', 'string', 'max:20'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
            'referral_code' => ['nullable', 'string', 'max:12'],
        ]);

        if (! $recaptcha->validate($request->input('g-recaptcha-response'), $request->ip())) {
            throw ValidationException::withMessages([
                'g-recaptcha-response' => 'reCAPTCHA verification failed. Please try again.',
            ]);
        }

        $referrer = $referrals->resolveReferrer($validated['referral_code'] ?? null);

        $user = User::create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'mobile' => $validated['mobile'] ?? null,
            'password' => $validated['password'],
        ]);

        // Credit the referrer (self-referral is impossible: the code must
        // belong to a different, already-existing user).
        if ($referrer !== null) {
            $referrals->rewardReferrer($user, $referrer);
        }

        event(new Registered($user));

        Auth::login($user);

        // OTP step only when the toggle is on and a mobile number was given.
        if (setting_bool('otp_enabled') && ! empty($user->mobile)) {
            $otp->send($user);

            return redirect()->route('otp.notice');
        }

        return redirect()->route('verification.notice');
    }
}
