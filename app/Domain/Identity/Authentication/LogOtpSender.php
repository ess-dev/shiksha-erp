<?php

namespace App\Domain\Identity\Authentication;

use App\Domain\Identity\Contracts\OtpSender;
use Illuminate\Support\Facades\Log;
use RuntimeException;

/**
 * Stand-in "SMS" until a provider is chosen: writes the code to the log so the
 * sign-in flow can be tried. The owner allowed this for development (2026-10-23)
 * and it refuses to run in production, where it would leak sign-in codes.
 */
class LogOtpSender implements OtpSender
{
    public function send(string $phoneE164, string $code, string $schoolName): void
    {
        if (app()->isProduction()) {
            throw new RuntimeException('The log OTP driver cannot be used in production. Configure an SMS provider.');
        }

        Log::info("[dev OTP] {$schoolName}: code {$code} for {$phoneE164}");
    }
}
