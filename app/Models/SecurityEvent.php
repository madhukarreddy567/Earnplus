<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SecurityEvent extends Model
{
    public const TYPE_IP_BLOCKED = 'ip_blocked';
    public const TYPE_RATE_LIMITED = 'rate_limited';
    public const TYPE_LOCKDOWN = 'lockdown';
    public const TYPE_KILL_SWITCH = 'kill_switch';

    protected $fillable = ['type', 'ip', 'path', 'user_agent', 'detail'];

    /**
     * Record a security event. Never throws — logging must not break requests.
     */
    public static function record(string $type, ?string $ip = null, ?string $path = null, ?string $detail = null): void
    {
        try {
            static::create([
                'type' => $type,
                'ip' => $ip,
                'path' => $path,
                'user_agent' => substr((string) request()->userAgent(), 0, 500),
                'detail' => $detail,
            ]);
        } catch (\Throwable) {
            // Logging failures must never break the request.
        }
    }

    /**
     * Keep the table small: drop events older than the retention window.
     */
    public static function prune(int $days = 30): int
    {
        return static::where('created_at', '<', now()->subDays($days))->delete();
    }
}
