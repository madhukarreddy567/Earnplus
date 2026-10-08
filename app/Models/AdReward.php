<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AdReward extends Model
{
    protected $fillable = [
        'user_id',
        'placement_id',
        'coins',
        'idempotency_key',
    ];

    protected function casts(): array
    {
        return [
            'coins' => 'integer',
        ];
    }

    public function placement(): BelongsTo
    {
        return $this->belongsTo(AdPlacement::class, 'placement_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
