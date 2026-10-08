<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

class Setting extends Model
{
    protected $fillable = ['key', 'value', 'group'];

    /**
     * Get a setting value by key, with optional default.
     * Only real DB values are cached — misses always fall through
     * to the caller's default so different defaults stay correct.
     */
    public static function get(string $key, mixed $default = null): mixed
    {
        $cacheKey = "setting.{$key}";

        if (Cache::has($cacheKey)) {
            return Cache::get($cacheKey);
        }

        $value = static::where('key', $key)->value('value');

        if ($value === null) {
            return $default;
        }

        Cache::forever($cacheKey, $value);

        return $value;
    }

    /**
     * Store a setting value by key (creates or updates) and bust the cache.
     */
    public static function set(string $key, mixed $value, string $group = 'general'): static
    {
        $setting = static::updateOrCreate(
            ['key' => $key],
            ['value' => $value === null ? null : (string) $value, 'group' => $group]
        );

        Cache::forget("setting.{$key}");

        return $setting;
    }

    /**
     * Get a setting as boolean (handles '1', 'true', 'on', 'yes').
     */
    public static function boolean(string $key, bool $default = false): bool
    {
        $value = static::get($key);

        if ($value === null) {
            return $default;
        }

        return filter_var($value, FILTER_VALIDATE_BOOLEAN);
    }

    /**
     * Get a setting as integer.
     */
    public static function integer(string $key, int $default = 0): int
    {
        $value = static::get($key);

        return $value === null ? $default : (int) $value;
    }

    /**
     * Clear the cached value for one key (used after external updates).
     */
    public static function flushCache(string $key): void
    {
        Cache::forget("setting.{$key}");
    }
}
