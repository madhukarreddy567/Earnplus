<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AdImpression extends Model
{
    protected $fillable = [
        'placement_id',
        'user_id',
        'ip',
        'device',
        'page',
        'rewarded',
    ];

    protected function casts(): array
    {
        return [
            'rewarded' => 'boolean',
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
