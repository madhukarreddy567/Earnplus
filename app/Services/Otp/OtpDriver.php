<?php

namespace App\Services\Otp;

use App\Models\User;

/**
 * Pluggable OTP delivery driver. The default implementation logs the
 * code (dev/test safe). Swap the container binding for a real SMS
 * gateway driver later — callers never change.
 */
interface OtpDriver
{
    /**
     * Deliver the plain-text code to the user.
     */
    public function deliver(User $user, string $code): void;
}
