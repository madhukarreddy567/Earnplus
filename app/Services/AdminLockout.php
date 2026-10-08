<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;

/**
 * Admin account lockout: 8 failed login attempts lock the account
 * for 15 minutes. Tracked per e-mail address in the cache.
 */
class AdminLockout
{
    public const MAX_ATTEMPTS = 8;

    public const LOCK_MINUTES = 15;

    public static function isLocked(string $email): bool
    {
        $until = Cache::get(self::untilKey($email));

        return $until !== null && now()->lt($until);
    }

    public static function recordFailure(string $email): void
    {
        $attempts = self::attempts($email) + 1;

        Cache::put(self::countKey($email), $attempts, now()->addMinutes(self::LOCK_MINUTES + 5));

        if ($attempts >= self::MAX_ATTEMPTS) {
            Cache::put(self::untilKey($email), now()->addMinutes(self::LOCK_MINUTES), now()->addMinutes(self::LOCK_MINUTES));
        }
    }

    public static function clear(string $email): void
    {
        Cache::forget(self::countKey($email));
        Cache::forget(self::untilKey($email));
    }

    public static function attempts(string $email): int
    {
        return (int) Cache::get(self::countKey($email), 0);
    }

    public static function lockedSecondsRemaining(string $email): int
    {
        $until = Cache::get(self::untilKey($email));

        return $until === null ? 0 : max(0, now()->diffInSeconds($until));
    }

    private static function baseKey(string $email): string
    {
        return 'admin_lockout:'.strtolower(trim($email));
    }

    private static function countKey(string $email): string
    {
        return self::baseKey($email).':count';
    }

    private static function untilKey(string $email): string
    {
        return self::baseKey($email).':until';
    }
}
