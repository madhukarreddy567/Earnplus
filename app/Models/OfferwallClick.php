<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OfferwallClick extends Model
{
    use HasFactory;

    public const STATUS_CLICKED = 'clicked';
    public const STATUS_CONVERTED = 'converted';
    public const STATUS_EXPIRED = 'expired';

    protected $fillable = [
        'provider_id',
        'user_id',
        'click_uid',
        'ip',
        'device_fingerprint',
        'status',
        'expires_at',
    ];

    protected $casts = [
        'expires_at' => 'datetime',
    ];

    public function provider(): BelongsTo
    {
        return $this->belongsTo(OfferwallProvider::class, 'provider_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function isExpired(): bool
    {
        return $this->expires_at->isPast();
    }

    public function isActive(): bool
    {
        return $this->status === self::STATUS_CLICKED && ! $this->isExpired();
    }
}
