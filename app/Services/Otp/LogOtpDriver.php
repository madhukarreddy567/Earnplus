<?php

namespace App\Services\Otp;

use App\Models\User;
use Illuminate\Support\Facades\Log;

/**
 * Default OTP driver: writes the code to the application log.
 * Safe for development and tests — no real SMS is ever sent.
 */
class LogOtpDriver implements OtpDriver
{
    public function deliver(User $user, string $code): void
    {
        Log::info("EarnPlus OTP for user #{$user->id} ({$user->email}, mobile {$user->mobile}): {$code}");
    }
}
