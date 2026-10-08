<?php

namespace App\Services;

use App\Models\OfferwallClick;
use App\Models\OfferwallProvider;
use App\Models\User;
use Illuminate\Support\Str;
use RuntimeException;

class ClickVelocityExceededException extends RuntimeException
{
}

/**
 * Provider-neutral offerwall engine: click tracking, device fingerprints,
 * velocity fraud checks and shared-device flagging.
 */
class OfferwallService
{
    /**
     * Stable device fingerprint from user agent + IP + a secret salt.
     * Stored, never reversible to the raw values.
     */
    public function fingerprint(?string $userAgent, ?string $ip): string
    {
        $salt = (string) setting('device_fingerprint_salt', 'earnplus');

        return hash('sha256', ($userAgent ?? '') . '|' . ($ip ?? '') . '|' . $salt);
    }

    /**
     * Record an outbound click. Enforces one active click per user+provider
     * (reuses the existing one) and a per-hour velocity cap.
     *
     * @throws ClickVelocityExceededException
     */
    public function trackClick(
        OfferwallProvider $provider,
        User $user,
        ?string $ip = null,
        ?string $userAgent = null
    ): OfferwallClick {
        $this->assertVelocityOk($user);

        $existing = OfferwallClick::where('provider_id', $provider->id)
            ->where('user_id', $user->id)
            ->where('status', OfferwallClick::STATUS_CLICKED)
            ->where('expires_at', '>', now())
            ->latest()
            ->first();

        if ($existing !== null) {
            return $existing;
        }

        return OfferwallClick::create([
            'provider_id' => $provider->id,
            'user_id' => $user->id,
            'click_uid' => Str::random(32),
            'ip' => $ip,
            'device_fingerprint' => $this->fingerprint($userAgent, $ip),
            'status' => OfferwallClick::STATUS_CLICKED,
            'expires_at' => now()->addHours(setting_int('offerwall_click_expiry_hours', 24)),
        ]);
    }

    /**
     * Find the click a postback refers to: by click_uid when given,
     * otherwise the user's latest still-active click for the provider.
     */
    public function resolveClick(
        OfferwallProvider $provider,
        User $user,
        ?string $clickUid
    ): ?OfferwallClick {
        if ($clickUid !== null && $clickUid !== '') {
            return OfferwallClick::where('provider_id', $provider->id)
                ->where('user_id', $user->id)
                ->where('click_uid', $clickUid)
                ->first();
        }

        return OfferwallClick::where('provider_id', $provider->id)
            ->where('user_id', $user->id)
            ->where('status', OfferwallClick::STATUS_CLICKED)
            ->where('expires_at', '>', now())
            ->latest()
            ->first();
    }

    /**
     * Fraud flags for a click: a device fingerprint seen across more than
     * 3 distinct users is flagged (multiple accounts on one device).
     *
     * @return string[]
     */
    public function fraudFlags(OfferwallClick $click): array
    {
        $flags = [];

        if ($click->device_fingerprint === null) {
            return $flags;
        }

        $distinctUsers = OfferwallClick::where('device_fingerprint', $click->device_fingerprint)
            ->distinct('user_id')
            ->count('user_id');

        if ($distinctUsers > 3) {
            $flags[] = 'shared_device';
        }

        return $flags;
    }

    /**
     * @throws ClickVelocityExceededException
     */
    protected function assertVelocityOk(User $user): void
    {
        $maxPerHour = setting_int('offerwall_max_clicks_per_hour', 20);

        $recent = OfferwallClick::where('user_id', $user->id)
            ->where('created_at', '>', now()->subHour())
            ->count();

        if ($recent >= $maxPerHour) {
            throw new ClickVelocityExceededException(
                "Too many offerwall clicks: {$recent} in the last hour (max {$maxPerHour})."
            );
        }
    }
}
