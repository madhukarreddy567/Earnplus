<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;

/**
 * Google reCAPTCHA v2 verification. Only active when the
 * recaptcha_enabled setting is on AND a secret key is configured —
 * otherwise forms work without it.
 */
class RecaptchaService
{
    public function enabled(): bool
    {
        return setting_bool('recaptcha_enabled')
            && (string) setting('recaptcha_secret_key', '') !== '';
    }

    public function siteKey(): string
    {
        return (string) setting('recaptcha_site_key', '');
    }

    /**
     * Verify a submitted token against Google's siteverify endpoint.
     * Returns true when reCAPTCHA is disabled (forms stay usable).
     */
    public function validate(?string $token, ?string $ip = null): bool
    {
        if (! $this->enabled()) {
            return true;
        }

        if (empty($token)) {
            return false;
        }

        try {
            $response = Http::asForm()->post('https://www.google.com/recaptcha/api/siteverify', [
                'secret' => setting('recaptcha_secret_key'),
                'response' => $token,
                'remoteip' => $ip,
            ]);

            return (bool) $response->json('success', false);
        } catch (\Throwable $e) {
            return false;
        }
    }
}
