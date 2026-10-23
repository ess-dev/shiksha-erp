<?php

namespace App\Domain\Identity\Exceptions;

use RuntimeException;

/**
 * Too many code requests for a phone, an address or a school. The limits apply
 * to every phone number alike, so this never reveals whether an account exists.
 */
class OtpThrottled extends RuntimeException
{
    public function __construct(public readonly int $retryAfterSeconds)
    {
        parent::__construct('Too many requests. Try again later.');
    }
}
