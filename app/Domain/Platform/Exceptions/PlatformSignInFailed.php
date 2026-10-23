<?php

namespace App\Domain\Platform\Exceptions;

use RuntimeException;

/**
 * Raised for every way Super Admin sign-in can fail, with one generic message.
 */
class PlatformSignInFailed extends RuntimeException
{
    public static function generic(): self
    {
        return new self(__('These credentials do not match our records.'));
    }
}
