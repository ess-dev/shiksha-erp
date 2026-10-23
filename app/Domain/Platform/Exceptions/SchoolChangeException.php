<?php

namespace App\Domain\Platform\Exceptions;

use DomainException;

/**
 * A change to a school that its current state does not allow.
 */
class SchoolChangeException extends DomainException
{
    public static function cannotSuspend(): self
    {
        return new self('Only an active or trial school can be suspended.');
    }

    public static function notSuspended(): self
    {
        return new self('This school is not suspended.');
    }

    public static function unknownModule(string $key): self
    {
        return new self("[{$key}] is not a sellable module.");
    }
}
