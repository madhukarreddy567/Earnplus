<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AdNetwork extends Model
{
    public const TYPE_ADSENSE = 'adsense';
    public const TYPE_ADSTERRA = 'adsterra';
    public const TYPE_MONETAG = 'monetag';
    public const TYPE_PROPELLERADS = 'propellerads';
    public const TYPE_ADMOB = 'admob';
    public const TYPE_UNITY_ADS = 'unity_ads';
    public const TYPE_CUSTOM = 'custom';

    /** @var list<string> */
    public const TYPES = [
        self::TYPE_ADSENSE,
        self::TYPE_ADSTERRA,
        self::TYPE_MONETAG,
        self::TYPE_PROPELLERADS,
        self::TYPE_ADMOB,
        self::TYPE_UNITY_ADS,
        self::TYPE_CUSTOM,
    ];

    /**
     * Config schema per network type: the keys the admin must fill in
     * the network's `config` JSON. Mobile-SDK networks (AdMob, Unity Ads)
     * render their creatives inside the Flutter app — the backend stores
     * the IDs and verifies their server-to-server reward callbacks.
     *
     * @return array<string, string> key => human label
     */
    public static function configSchema(string $type): array
    {
        return match ($type) {
            self::TYPE_ADMOB => [
                'app_id' => 'AdMob App ID (ca-app-pub-…)',
                'rewarded_ad_unit_id' => 'Rewarded ad unit ID (ca-app-pub-…/…)',
                'ssv_key_id' => 'SSV key ID (AdMob → SSV key pairs, optional but recommended)',
            ],
            self::TYPE_UNITY_ADS => [
                'game_id_android' => 'Unity Game ID (Android)',
                'game_id_ios' => 'Unity Game ID (iOS)',
                'rewarded_placement_id' => 'Rewarded placement ID',
                'rewarded_ad_unit_id' => 'Rewarded Ad Unit ID (dashboard reference)',
                'banner_placement_id' => 'Banner placement ID (stored for later use)',
                'interstitial_placement_id' => 'Interstitial placement ID',
                'interstitial_ad_unit_id' => 'Interstitial Ad Unit ID (dashboard reference)',
                's2s_secret' => 'Server-to-server callback secret (HMAC)',
            ],
            default => [],
        };
    }

    /**
     * True when this network renders in the mobile SDK (Flutter) rather
     * than as web code in a placement slot.
     */
    public function isMobileSdk(): bool
    {
        return in_array($this->type, [self::TYPE_ADMOB, self::TYPE_UNITY_ADS], true);
    }

    protected $fillable = [
        'name',
        'slug',
        'enabled',
        'type',
        'config',
    ];

    protected function casts(): array
    {
        return [
            'enabled' => 'boolean',
            'config' => 'array',
        ];
    }

    public function placements(): HasMany
    {
        return $this->hasMany(AdPlacement::class, 'network_id');
    }

    public static function typeLabel(string $type): string
    {
        return match ($type) {
            self::TYPE_ADSENSE => 'Google AdSense',
            self::TYPE_ADSTERRA => 'Adsterra',
            self::TYPE_MONETAG => 'Monetag',
            self::TYPE_PROPELLERADS => 'PropellerAds',
            self::TYPE_ADMOB => 'Google AdMob (mobile SDK)',
            self::TYPE_UNITY_ADS => 'Unity Ads (mobile SDK)',
            default => 'Custom',
        };
    }
}
