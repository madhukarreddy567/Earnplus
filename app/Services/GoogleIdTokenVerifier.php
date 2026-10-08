<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;

/**
 * Verifies a Google ID token coming from the Flutter app's
 * `google_sign_in` flow against Google's tokeninfo endpoint.
 *
 * Returns [sub, email, name, picture] on success, null otherwise.
 * The `aud` claim must match our configured Google client ID —
 * a token minted for any other app is rejected.
 */
class GoogleIdTokenVerifier
{
    /**
     * @return array{sub: string, email: ?string, name: ?string, picture: ?string}|null
     */
    public function verify(string $idToken): ?array
    {
        $allowedAudiences = array_filter([
            setting('google_client_id'),
            setting('google_client_id_android'),
            setting('google_client_id_ios'),
        ]);

        if (empty($allowedAudiences)) {
            return null;
        }

        try {
            $response = Http::timeout(10)->get(
                'https://oauth2.googleapis.com/tokeninfo',
                ['id_token' => $idToken]
            );
        } catch (\Throwable) {
            return null;
        }

        if (! $response->successful()) {
            return null;
        }

        $claims = $response->json();

        if (! in_array($claims['aud'] ?? null, $allowedAudiences, true)) {
            return null;
        }

        if (empty($claims['sub'])) {
            return null;
        }

        return [
            'sub' => (string) $claims['sub'],
            'email' => $claims['email'] ?? null,
            'name' => $claims['name'] ?? null,
            'picture' => $claims['picture'] ?? null,
        ];
    }
}
