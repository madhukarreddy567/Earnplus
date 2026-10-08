<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class OfferwallProvider extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'slug',
        'enabled',
        'postback_secret',
        'ip_whitelist',
        'user_revenue_share',
        'config',
        'sandbox_mode',
    ];

    protected $casts = [
        'enabled' => 'boolean',
        'user_revenue_share' => 'float',
        'config' => 'array',
        'sandbox_mode' => 'boolean',
    ];

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    public function clicks(): HasMany
    {
        return $this->hasMany(OfferwallClick::class, 'provider_id');
    }

    public function conversions(): HasMany
    {
        return $this->hasMany(OfferwallConversion::class, 'provider_id');
    }

    /**
     * Generate a fresh, unguessable postback secret.
     */
    public static function generateSecret(): string
    {
        return Str::random(64);
    }

    /**
     * Parsed IP whitelist: one IP or CIDR per line/comma.
     *
     * @return string[]
     */
    public function ipWhitelist(): array
    {
        if ($this->ip_whitelist === null || trim($this->ip_whitelist) === '') {
            return [];
        }

        return array_values(array_filter(array_map(
            fn (string $line) => trim($line),
            preg_split('/[\s,]+/', $this->ip_whitelist)
        )));
    }

    /**
     * Whether the given IP passes the (optional) whitelist.
     */
    public function ipAllowed(?string $ip): bool
    {
        $list = $this->ipWhitelist();

        if ($list === []) {
            return true;
        }

        if ($ip === null) {
            return false;
        }

        foreach ($list as $entry) {
            if (str_contains($entry, '/')) {
                if (self::ipInCidr($ip, $entry)) {
                    return true;
                }
            } elseif ($entry === $ip) {
                return true;
            }
        }

        return false;
    }

    /**
     * Coins the user keeps from a provider payout, after the revenue share.
     * Always at least 1 coin when the payout is positive.
     */
    public function userCoinsFor(int $payoutCoins): int
    {
        if ($payoutCoins <= 0) {
            return 0;
        }

        return (int) max(1, floor($payoutCoins * $this->user_revenue_share / 100));
    }

    protected static function ipInCidr(string $ip, string $cidr): bool
    {
        [$subnet, $bits] = explode('/', $cidr, 2);

        $ipLong = ip2long($ip);
        $subnetLong = ip2long($subnet);

        if ($ipLong === false || $subnetLong === false) {
            return false;
        }

        $mask = -1 << (32 - (int) $bits);

        return ($ipLong & $mask) === ($subnetLong & $mask);
    }
}
