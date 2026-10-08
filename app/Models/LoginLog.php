<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;

class LoginLog extends Model
{
    protected $fillable = [
        'email',
        'ip',
        'user_agent',
        'success',
        'guard',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'success' => 'boolean',
        ];
    }

    /**
     * Record a login attempt (success or failure) for either guard.
     */
    public static function record(?string $email, Request $request, bool $success, string $guard): static
    {
        return static::create([
            'email' => $email,
            'ip' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'success' => $success,
            'guard' => $guard,
        ]);
    }
}
