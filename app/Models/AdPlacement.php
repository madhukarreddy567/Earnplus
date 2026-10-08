<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AdPlacement extends Model
{
    public const TYPE_BANNER = 'banner';
    public const TYPE_POPUNDER = 'popunder';
    public const TYPE_DIRECT_LINK = 'direct_link';
    public const TYPE_NATIVE = 'native';
    public const TYPE_SIDEBAR = 'sidebar';
    public const TYPE_INTERSTITIAL = 'interstitial';
    public const TYPE_REWARDED = 'rewarded';

    /** @var list<string> */
    public const TYPES = [
        self::TYPE_BANNER,
        self::TYPE_POPUNDER,
        self::TYPE_DIRECT_LINK,
        self::TYPE_NATIVE,
        self::TYPE_SIDEBAR,
        self::TYPE_INTERSTITIAL,
        self::TYPE_REWARDED,
    ];

    public const DEVICE_ALL = 'all';
    public const DEVICE_MOBILE = 'mobile';
    public const DEVICE_DESKTOP = 'desktop';

    /** @var list<string> */
    public const DEVICES = [
        self::DEVICE_ALL,
        self::DEVICE_MOBILE,
        self::DEVICE_DESKTOP,
    ];

    /** @var list<string> Well-known slot keys used by the app views. */
    public const SLOTS = [
        'dashboard-banner',
        'tasks-native',
        'sidebar',
        'interstitial',
        'rewarded',
    ];

    protected $fillable = [
        'network_id',
        'name',
        'slug',
        'slot',
        'enabled',
        'placement_type',
        'device',
        'pages',
        'frequency_cap_per_session',
        'priority',
        'coins',
        'custom_code',
    ];

    protected function casts(): array
    {
        return [
            'enabled' => 'boolean',
            'pages' => 'array',
            'frequency_cap_per_session' => 'integer',
            'priority' => 'integer',
            'coins' => 'integer',
        ];
    }

    public function network(): BelongsTo
    {
        return $this->belongsTo(AdNetwork::class, 'network_id');
    }

    public function impressions(): HasMany
    {
        return $this->hasMany(AdImpression::class, 'placement_id');
    }

    public function rewards(): HasMany
    {
        return $this->hasMany(AdReward::class, 'placement_id');
    }

    public function isRewarded(): bool
    {
        return $this->placement_type === self::TYPE_REWARDED;
    }

    /**
     * A placement can only serve when it AND its network are enabled.
     */
    public function isServable(): bool
    {
        return $this->enabled && (bool) $this->network?->enabled;
    }

    public static function typeLabel(string $type): string
    {
        return match ($type) {
            self::TYPE_BANNER => 'Banner',
            self::TYPE_POPUNDER => 'Popunder',
            self::TYPE_DIRECT_LINK => 'Direct link',
            self::TYPE_NATIVE => 'Native',
            self::TYPE_SIDEBAR => 'Sidebar',
            self::TYPE_INTERSTITIAL => 'Interstitial',
            self::TYPE_REWARDED => 'Rewarded',
            default => ucfirst(str_replace('_', ' ', $type)),
        };
    }
}
