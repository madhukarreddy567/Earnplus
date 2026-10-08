<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Services\GoogleUserResolver;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Laravel\Socialite\Facades\Socialite;

/**
 * "Continue with Google" sign-in (Laravel Socialite).
 *
 * The feature is fully inert until the owner pastes Google OAuth
 * credentials (client ID + secret) into the settings — then the
 * buttons appear and these routes work. Redirect URI registered in
 * Google Cloud: {APP_URL}/auth/google/callback
 *
 * - New Google user  → account auto-created, e-mail marked verified
 *   (Google already verified it), signup bonus credited through the
 *   same idempotent path as e-mail registration.
 * - Existing e-mail user → Google account linked to the same row.
 */
class GoogleAuthController extends Controller
{
    /**
     * Send the user to Google's OAuth consent screen.
     * A ?ref= referral code is stashed in the session for the callback.
     */
    public function redirect(Request $request): RedirectResponse
    {
        abort_unless(google_auth_enabled(), 404);

        if ($request->filled('ref')) {
            $request->session()->put('google_oauth_ref', $request->query('ref'));
        }

        return $this->driver()->redirect();
    }

    /**
     * Handle Google's callback: find, link, or create the user.
     */
    public function callback(Request $request, GoogleUserResolver $resolver): RedirectResponse
    {
        abort_unless(google_auth_enabled(), 404);

        $googleUser = $this->driver()->user();

        $user = $resolver->resolve(
            $googleUser->getId(),
            $googleUser->getEmail(),
            $googleUser->getName(),
            $googleUser->getAvatar(),
            $request->session()->pull('google_oauth_ref')
        );

        $request->session()->forget('google_oauth_ref');

        Auth::login($user, true);
        $request->session()->regenerate();

        return redirect()->intended(route('dashboard'));
    }

    /**
     * Socialite Google driver configured from DB settings at runtime —
     * credentials are never in .env or config files.
     */
    protected function driver(): \Laravel\Socialite\Contracts\Provider
    {
        config([
            'services.google' => [
                'client_id' => setting('google_client_id'),
                'client_secret' => setting('google_client_secret'),
                'redirect' => rtrim(config('app.url'), '/') . '/auth/google/callback',
            ],
        ]);

        return Socialite::driver('google');
    }
}
