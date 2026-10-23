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

    public static function teacherAlreadyLeads(): self
    {
        return new self('That teacher already leads another class this year.');
    }

    public static function duplicate(string $what): self
    {
        return new self("{$what} already exists.");
    }

    public static function subjectInUse(): self
    {
        return new self('This subject is still taught in a class or has teachers chosen, so it cannot be deleted.');
    }

    public static function needsExaminedSubject(): self
    {
        return new self('Pick at least one examined subject.');
    }

    public static function wouldLeaveNoExaminedSubject(): self
    {
        return new self('Making this co-scholastic would leave a class with no examined subject.');
    }

    public static function subjectNotTaught(): self
    {
        return new self('That subject is not taught in this class.');
    }

    public static function teacherUnavailable(): self
    {
        return new self('That teacher is not available.');
    }
}
