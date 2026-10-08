<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SpinHistory extends Model
{
    protected $table = 'spin_history';

    protected $fillable = [
        'user_id',
        'result_amount',
        'won',
        'ip',
        'device_fingerprint',
    ];

    protected function casts(): array
    {
        return [
            'result_amount' => 'integer',
            'won' => 'boolean',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
