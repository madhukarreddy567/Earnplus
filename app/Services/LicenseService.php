<?php

namespace App\Services;

use App\Models\LicenseViolation;
use App\Models\SecurityEvent;
use App\Models\Setting;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

/**
 * Phase 13: production/license/domain lock.
 *
 * The build is signed with the constant SIGNATURE. The owner configures
 * `app_mode` (development/production), `licensed_domain` and optional
 * aliases from the admin license page; everything lives in DB settings
 * (nothing hardcoded) except the signature itself.
 *
 * - Development mode: lenient — license checks pass, the demo provider
 *   stays visible, a small "development mode" ribbon shows in the UI.
 * - Production mode: strict — the signature must match, requests must
 *   arrive on the licensed domain (or alias), violations are logged,
 *   and the kill switch locks every user route.
 *
 * Note: only the signature constant below is a fixed value. No license
 * key values are invented or exposed anywhere in this codebase.
 */
class LicenseService
{
    /**
     * The build signature. The license is valid only while the stored
     * `license_signature` setting equals this value.
     */
    public const SIGNATURE = 'HANDRIKAMADHUKARREDDY';

    public const MODE_DEVELOPMENT = 'development';
    public const MODE_PRODUCTION = 'production';

    /**
     * Current app mode: DB setting wins, APP_MODE env is the fallback,
     * development is the safe default.
     */
    public function mode(): string
    {
        $mode = strtolower(trim((string) setting('app_mode', env('APP_MODE', self::MODE_DEVELOPMENT))));

        return $mode === self::MODE_PRODUCTION ? self::MODE_PRODUCTION : self::MODE_DEVELOPMENT;
    }

    public function isProduction(): bool
    {
        return $this->mode() === self::MODE_PRODUCTION;
    }

    public function isDevelopment(): bool
    {
        return ! $this->isProduction();
    }

    /**
     * The stored signature must exactly match the build signature.
     */
    public function signatureValid(): bool
    {
        return hash_equals(self::SIGNATURE, (string) setting('license_signature', ''));
    }

    public function isLocked(): bool
    {
        return setting_bool('license_locked', false);
    }

    public function licensedDomain(): ?string
    {
        $domain = trim((string) setting('licensed_domain', ''));

        return $domain === '' ? null : $this->normalizeHost($domain);
    }

    /**
     * Configured extra hostnames (comma/space separated).
     *
     * @return list<string>
     */
    public function domainAliases(): array
    {
        $raw = (string) setting('license_domain_aliases', '');

        $aliases = [];
        foreach (preg_split('/[\s,;]+/', $raw) as $part) {
            $part = $this->normalizeHost(trim($part));
            if ($part !== '') {
                $aliases[] = $part;
            }
        }

        return array_values(array_unique($aliases));
    }

    /**
     * Does this request host belong to the licensed domain?
     * The www variant is always accepted both ways, plus aliases.
     */
    public function hostAllowed(string $host): bool
    {
        $domain = $this->licensedDomain();

        if ($domain === null) {
            return true; // Not configured yet — admin sees a warning instead.
        }

        $host = $this->normalizeHost($host);

        if ($host === $domain) {
            return true;
        }

        // www variant accepted both ways: licensed example.com serves
        // www.example.com, and vice versa.
        if ($host === 'www.' . $domain || $domain === 'www.' . $host) {
            return true;
        }

        return in_array($host, $this->domainAliases(), true);
    }

    /**
     * Violation-block threshold: after this many domain/signature
     * violations from one IP in 24h, requests are hard-blocked with a
     * neutral 403. 0 disables the block.
     */
    public function violationThreshold(): int
    {
        return max(0, setting_int('license_violation_block_threshold', 10));
    }

    public function violationThresholdExceeded(string $ip): bool
    {
        $threshold = $this->violationThreshold();

        if ($threshold <= 0) {
            return false;
        }

        $count = LicenseViolation::query()
            ->where('ip', $ip)
            ->whereIn('type', [
                LicenseViolation::TYPE_DOMAIN_MISMATCH,
                LicenseViolation::TYPE_SIGNATURE_TAMPER,
            ])
            ->where('created_at', '>=', now()->subDay())
            ->count();

        return $count >= $threshold;
    }

    /**
     * Record a license violation. Never throws — logging must not break
     * requests. Dedupe keeps one violation from flooding the log on
     * every request (per type + IP + host within the window).
     */
    public function recordViolation(
        string $type,
        ?Request $request = null,
        array $details = [],
        int $dedupeMinutes = 60
    ): void {
        try {
            $ip = $request?->ip();
            $host = $request ? $this->normalizeHost($request->getHost()) : null;

            $recent = LicenseViolation::query()
                ->where('type', $type)
                ->where('ip', $ip)
                ->where('host', $host)
                ->where('created_at', '>=', now()->subMinutes($dedupeMinutes))
                ->exists();

            if ($recent) {
                return;
            }

            LicenseViolation::create([
                'type' => $type,
                'ip' => $ip,
                'host' => $host,
                'details' => $details === [] ? null : $details,
            ]);
        } catch (\Throwable) {
            // Violation logging must never break the app.
        }
    }

    /**
     * Boot-time signature verification (called from AppServiceProvider).
     * In production, a tampered signature is a violation.
     */
    public function verifyBootSignature(): void
    {
        if (! $this->isProduction()) {
            return;
        }

        if (! $this->signatureValid()) {
            $this->recordViolation(LicenseViolation::TYPE_SIGNATURE_TAMPER, null, [
                'source' => 'boot',
            ]);
        }
    }

    /**
     * Emergency kill switch: lock every user route immediately.
     * Admin login still works so the owner can unlock.
     */
    public function activateKillSwitch(string $reason = 'manual'): void
    {
        Setting::set('license_locked', '1', 'license');
        Setting::set('license_locked_at', now()->toDateTimeString(), 'license');
        Setting::set('license_locked_reason', substr($reason, 0, 255), 'license');

        $this->recordViolation(LicenseViolation::TYPE_KILL_SWITCH, request(), [
            'reason' => $reason,
            'action' => 'activated',
        ], 0);

        SecurityEvent::record(SecurityEvent::TYPE_KILL_SWITCH, request()?->ip(), request()?->path(), $reason);
    }

    public function deactivateKillSwitch(): void
    {
        Setting::set('license_locked', '0', 'license');
        Setting::set('license_locked_reason', '', 'license');

        $this->recordViolation(LicenseViolation::TYPE_KILL_SWITCH, request(), [
            'action' => 'deactivated',
        ], 0);

        SecurityEvent::record(SecurityEvent::TYPE_KILL_SWITCH, request()?->ip(), request()?->path(), 'kill switch deactivated');
    }

    /**
     * Remote kill secret: generated once, shown only on the admin license
     * page (regeneratable). The remote-kill URL stays disabled until the
     * owner explicitly enables it.
     */
    public function killSecret(): string
    {
        $secret = (string) setting('license_kill_secret', '');

        if ($secret === '') {
            $secret = Str::random(48);
            Setting::set('license_kill_secret', $secret, 'license');
        }

        return $secret;
    }

    public function regenerateKillSecret(): string
    {
        $secret = Str::random(48);
        Setting::set('license_kill_secret', $secret, 'license');

        return $secret;
    }

    public function remoteKillEnabled(): bool
    {
        return setting_bool('license_remote_kill_enabled', false);
    }

    /**
     * The signed token the remote-kill URL must carry.
     */
    public function remoteKillToken(): string
    {
        return hash_hmac('sha256', 'earnplus-remote-kill', $this->killSecret());
    }

    public function remoteKillTokenValid(?string $token): bool
    {
        if (! is_string($token) || $token === '') {
            return false;
        }

        return hash_equals($this->remoteKillToken(), $token);
    }

    /**
     * Everything the admin license page needs, in one array.
     */
    public function status(): array
    {
        $mode = $this->mode();
        $domain = $this->licensedDomain();
        $locked = $this->isLocked();

        $state = 'ok';
        $notes = [];

        if ($locked) {
            $state = 'locked';
            $notes[] = 'Kill switch is active — all user routes are locked.';
        } elseif ($mode === self::MODE_PRODUCTION) {
            if (! $this->signatureValid()) {
                $state = 'violation';
                $notes[] = 'License signature does not match this build.';
            } elseif ($domain === null) {
                $state = 'warning';
                $notes[] = 'Production mode is on but no licensed domain is configured — set it below.';
            }
        } else {
            $notes[] = 'Development mode — license checks are lenient.';
        }

        return [
            'mode' => $mode,
            'is_production' => $mode === self::MODE_PRODUCTION,
            'signature_valid' => $this->signatureValid(),
            'licensed_domain' => $domain,
            'domain_aliases' => $this->domainAliases(),
            'locked' => $locked,
            'locked_at' => setting('license_locked_at', ''),
            'locked_reason' => setting('license_locked_reason', ''),
            'remote_kill_enabled' => $this->remoteKillEnabled(),
            'state' => $state, // ok | warning | violation | locked
            'notes' => $notes,
            'violations_24h' => LicenseViolation::query()
                ->where('created_at', '>=', now()->subDay())
                ->count(),
        ];
    }

    /**
     * Admin dashboard warnings (null when everything is fine).
     *
     * @return list<string>
     */
    public function adminAlerts(): array
    {
        $alerts = [];
        $status = $this->status();

        if ($status['locked']) {
            $alerts[] = '🔐 Kill switch is ACTIVE — users see the lock page. Unlock from the license page.';
        }

        if ($status['state'] === 'violation') {
            $alerts[] = '🔐 License signature mismatch — the app is serving the unlicensed page in production.';
        }

        if ($status['state'] === 'warning') {
            $alerts[] = '🔐 Production mode is on but no licensed domain is set. Configure it on the license page.';
        }

        return $alerts;
    }

    protected function normalizeHost(string $host): string
    {
        $host = strtolower(trim($host));

        // Strip any port the host may carry.
        if (str_contains($host, ':')) {
            $host = explode(':', $host, 2)[0];
        }

        return rtrim($host, '.');
    }
}
