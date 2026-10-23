<?php

namespace App\Domain\School\Exceptions;

use DomainException;

/**
 * A change to the school structure that the rules do not allow.
 */
class StructureException extends DomainException
{
    public static function notAllowed(): self
    {
        return new self('You are not allowed to change the school structure here.', 403);
    }

    public static function yearEndsBeforeItStarts(): self
    {
        return new self('An academic year must end after it starts.');
    }

    public static function yearOverlaps(string $other): self
    {
        return new self("The academic year overlaps [{$other}].");
    }

    public static function campusInactive(): self
    {
        return new self('Sections can only be added to an active campus.');
    }

    public static function duplicate(string $what): self
    {
        return new self("{$what} already exists.");
    }
}
