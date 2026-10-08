<?php

namespace App\Services;

use App\Models\OtpCode;
use App\Models\User;
use App\Services\Otp\OtpDriver;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;

/**
 * Generates and verifies 6-digit one-time codes for mobile verification.
 * Codes are bcrypt-hashed at rest, single-use, and expire after 10 minutes.
 */
class OtpService
{
    public const EXPIRY_MINUTES = 10;

    public const PURPOSE_MOBILE = 'mobile_verify';

    public function __construct(private OtpDriver $driver)
    {
    }

    /**
     * Generate a fresh code for the user, invalidating any previous live one.
     */
    public function send(User $user, string $purpose = self::PURPOSE_MOBILE): void
    {
        OtpCode::where('user_id', $user->id)
            ->where('purpose', $purpose)
            ->whereNull('consumed_at')
            ->delete();

        $code = (string) random_int(100000, 999999);

        OtpCode::create([
            'user_id' => $user->id,
            'code' => Hash::make($code),
            'purpose' => $purpose,
            'expires_at' => now()->addMinutes(self::EXPIRY_MINUTES),
        ]);

        $this->driver->deliver($user, $code);

        // Test hook: lets feature tests read the code back without
        // ever storing it in plain text in the database.
        if (app()->runningUnitTests()) {
            Cache::put($this->testKey($user->id), $code, 600);
        }
    }

    /**
     * Verify a submitted code. Consumes it on success (single use).
     */
    public function verify(User $user, string $code, string $purpose = self::PURPOSE_MOBILE): bool
    {
        $record = OtpCode::where('user_id', $user->id)
            ->where('purpose', $purpose)
            ->whereNull('consumed_at')
            ->latest()
            ->first();

        if ($record === null || ! $record->isLive()) {
            return false;
        }

        if (! Hash::check($code, $record->code)) {
            return false;
        }

        $record->update(['consumed_at' => now()]);

        return true;
    }

    /**
     * Read back the last generated code — tests only.
     */
    public function plainCodeForTest(User $user): ?string
    {
        return Cache::get($this->testKey($user->id));
    }

    private function testKey(int $userId): string
    {
        return "otp.plain.{$userId}";
    }
}
