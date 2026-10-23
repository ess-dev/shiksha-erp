<?php

namespace App\Domain\Identity\Contracts;

/**
 * Delivers a one-time code by SMS. The provider is still an open decision, so
 * the app only knows this interface (see `config('shiksha.otp.driver')`).
 */
interface OtpSender
{
    public function send(string $phoneE164, string $code, string $schoolName): void;
}
