<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\GoogleIdTokenVerifier;
use App\Services\GoogleUserResolver;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

/**
 * Mobile (Sanctum token) authentication.
 *
 * - Google: the Flutter app signs the user in with the native
 *   `google_sign_in` SDK, then POSTs the ID token here. The token is
 *   verified with Google and the user is resolved through the SAME
 *   find/link/create logic as the web Socialite flow
 *   (GoogleUserResolver) — signup bonus and referral handling included.
 * - E-mail: mirrors the web login, but returns 404 while the
 *   `email_auth_enabled` toggle is off, exactly like the web routes.
 */
class AuthController extends Controller
{
    /**
     * Exchange a Google ID token for a Sanctum API token.
     */
    public function google(
        Request $request,
        GoogleIdTokenVerifier $verifier,
        GoogleUserResolver $resolver
    ): JsonResponse {
        abort_unless(google_auth_enabled(), 404, 'Google sign-in is not configured.');

        $data = $request->validate([
            'id_token' => ['required', 'string', 'max:4096'],
            'ref' => ['nullable', 'string', 'max:32'],
        ]);

        $claims = $verifier->verify($data['id_token']);

        if ($claims === null || empty($claims['email'])) {
            return response()->json(['message' => 'Invalid Google token.'], 401);
        }

        $user = $resolver->resolve(
            $claims['sub'],
            $claims['email'],
            $claims['name'],
            $claims['picture'],
            $data['ref'] ?? null
        );

        return response()->json([
            'token' => $user->createToken('earnplus-mobile')->plainTextToken,
            'user' => $this->userPayload($user),
        ]);
    }

    /**
     * E-mail + password login. 404 while the admin toggle is off.
     */
    public function login(Request $request): JsonResponse
    {
        abort_unless(setting_bool('email_auth_enabled', false), 404);

        $data = $request->validate([
            'email' => ['required', 'email', 'max:190'],
            'password' => ['required', 'string'],
        ]);

        $user = User::where('email', $data['email'])->first();

        if (! $user || ! Hash::check($data['password'], $user->password)) {
            throw ValidationException::withMessages([
                'email' => ['The provided credentials are incorrect.'],
            ]);
        }

        return response()->json([
            'token' => $user->createToken('earnplus-mobile')->plainTextToken,
            'user' => $this->userPayload($user),
        ]);
    }

    /**
     * Revoke the current token.
     */
    public function logout(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json(['message' => 'Logged out.']);
    }

    /**
     * The authenticated user's profile.
     */
    public function me(Request $request): JsonResponse
    {
        return response()->json(['user' => $this->userPayload($request->user())]);
    }

    /**
     * @return array<string, mixed>
     */
    protected function userPayload(User $user): array
    {
        $user->loadMissing('wallet');

        return [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'avatar' => $user->avatar,
            'referral_code' => $user->referral_code,
            'coins' => $user->coinBalance(),
            'rupees' => coins_to_rupees($user->coinBalance()),
        ];
    }
}
