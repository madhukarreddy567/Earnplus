<?php

namespace App\Models;

use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A date-based promotion that multiplies coin earnings.
 *
 * Scope decides which earning paths it touches: 'global' applies
 * everywhere, any other scope applies only to that earning path.
 * provider_id narrows a task_offerwall promotion to one provider.
 */
class Promotion extends Model
{
    public const SCOPE_GLOBAL = 'global';
    public const SCOPE_TASK_OFFERWALL = 'task_offerwall';
    public const SCOPE_SPIN = 'spin';
    public const SCOPE_DAILY_CHECKIN = 'daily_checkin';
    public const SCOPE_REWARDED_AD = 'rewarded_ad';
    public const SCOPE_REFERRAL_BONUS = 'referral_bonus';

    public const SCOPES = [
        self::SCOPE_GLOBAL,
        self::SCOPE_TASK_OFFERWALL,
        self::SCOPE_SPIN,
        self::SCOPE_DAILY_CHECKIN,
        self::SCOPE_REWARDED_AD,
        self::SCOPE_REFERRAL_BONUS,
    ];

    public const SCOPE_LABELS = [
        self::SCOPE_GLOBAL => 'Global (everywhere)',
        self::SCOPE_TASK_OFFERWALL => 'Tasks / offerwalls',
        self::SCOPE_SPIN => 'Spin wheel',
        self::SCOPE_DAILY_CHECKIN => 'Daily check-in',
        self::SCOPE_REWARDED_AD => 'Rewarded ads',
        self::SCOPE_REFERRAL_BONUS => 'Referral bonus',
    ];

    protected $fillable = [
        'name',
        'slug',
        'enabled',
        'multiplier',
        'scope',
        'provider_id',
        'starts_at',
        'ends_at',
        'banner_title',
        'banner_subtitle',
        'badge_text',
        'priority',
        'created_by',
    ];

    protected $casts = [
        'enabled' => 'boolean',
        'multiplier' => 'decimal:2',
        'starts_at' => 'datetime',
        'ends_at' => 'datetime',
        'priority' => 'integer',
    ];

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    public function provider(): BelongsTo
    {
        return $this->belongsTo(OfferwallProvider::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(Admin::class, 'created_by');
    }

    /**
     * Is this promotion live right now (or at the given moment)?
     */
    public function isLive(?CarbonInterface $at = null): bool
    {
        $at = $at ?? now();

        return $this->enabled
            && $this->starts_at <= $at
            && $this->ends_at >= $at;
    }

    /**
     * Lifecycle status for the admin list: active / upcoming / expired.
     */
    public function status(): string
    {
        $now = now();

        if (! $this->enabled) {
            return 'disabled';
        }

        if ($this->starts_at > $now) {
            return 'upcoming';
        }

        if ($this->ends_at < $now) {
            return 'expired';
        }

        return 'active';
    }

    /**
     * Badge text for UI tiles, e.g. "2X". Falls back to a value
     * derived from the multiplier when the admin left it blank.
     */
    public function badgeText(): string
    {
        if (! empty($this->badge_text)) {
            return $this->badge_text;
        }

        $m = (float) $this->multiplier;
        $label = $m == (int) $m ? (string) (int) $m : rtrim(rtrim(number_format($m, 2), '0'), '.');

        return $label . 'X';
    }

    public function scopeLabel(): string
    {
        return self::SCOPE_LABELS[$this->scope] ?? $this->scope;
    }
}
