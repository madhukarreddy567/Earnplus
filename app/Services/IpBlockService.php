<?php

namespace App\Services;

use App\Models\BlockedIp;
use Symfony\Component\HttpFoundation\IpUtils;

/**
 * IP blocklist checks (Phase 10).
 *
 * - The whitelist (`security_ip_whitelist` setting, one IP or CIDR per line)
 *   always wins: whitelisted clients are never blocked.
 * - The blocklist (`blocked_ips` table) supports single IPs and CIDR ranges;
 *   expired entries are ignored.
 */
class IpBlockService
{
    /**
     * Is this client IP currently blocked?
     */
    public static function isBlocked(string $ip): bool
    {
        $ip = trim($ip);
        if ($ip === '') {
            return false;
        }

        if (self::isWhitelisted($ip)) {
            return false;
        }

        foreach (self::activeBlocks() as $block) {
            if (IpUtils::checkIp($ip, $block->ip_or_cidr)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Validate an IP-or-CIDR value for the blocklist form.
     */
    public static function isValidIpOrCidr(string $value): bool
    {
        $value = trim($value);

        if (filter_var($value, FILTER_VALIDATE_IP)) {
            return true;
        }

        if (str_contains($value, '/')) {
            [$base, $bits] = explode('/', $value, 2) + [null, null];
            if (! filter_var($base, FILTER_VALIDATE_IP) || ! is_numeric($bits)) {
                return false;
            }
            $bits = (int) $bits;
            $max = str_contains($base, ':') ? 128 : 32;

            return $bits >= 0 && $bits <= $max;
        }

        return false;
    }

    /**
     * @return \Illuminate\Support\Collection<int, BlockedIp>
     */
    protected static function activeBlocks()
    {
        try {
            return BlockedIp::query()
                ->where(function ($q) {
                    $q->whereNull('expires_at')->orWhere('expires_at', '>', now());
                })
                ->get();
        } catch (\Throwable) {
            // Database not ready (fresh install mid-migrate) — fail open
            // rather than 500ing every request on a security check.
            return collect();
        }
    }

    protected static function isWhitelisted(string $ip): bool
    {
        $raw = (string) setting('security_ip_whitelist', '');
        $entries = array_filter(array_map('trim', preg_split('/[\r\n,]+/', $raw)));

        foreach ($entries as $entry) {
            if (IpUtils::checkIp($ip, $entry)) {
                return true;
            }
        }

        return false;
    }
}
