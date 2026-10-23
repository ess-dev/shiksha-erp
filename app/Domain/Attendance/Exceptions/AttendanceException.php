<?php

namespace App\Domain\Attendance\Exceptions;

use DomainException;

/**
 * A register change the attendance rules do not allow. Code 403 is a refusal,
 * 404 a section outside the person's reach (shown as not found), anything
 * else a message shown on the register.
 */
class AttendanceException extends DomainException
{
    public static function notAllowed(): self
    {
        return new self('You are not allowed to change this register.', 403);
    }

    public static function notFound(): self
    {
        return new self('That class was not found.', 404);
    }

    public static function invalidDate(): self
    {
        return new self('Pick a real date.');
    }

    public static function futureDate(): self
    {
        return new self('A register cannot be saved for a day that has not happened yet.');
    }

    public static function beforeYear(): self
    {
        return new self('That date is before the start of the current academic year.');
    }

    public static function closedDay(string $reason): self
    {
        return new self("There is no register on that day: {$reason}.");
    }

    public static function locked(int $days): self
    {
        return new self("Registers older than {$days} days are locked. Ask someone who can correct attendance.");
    }

    public static function incomplete(int $missing): self
    {
        return new self($missing === 1 ? 'Mark 1 more student before saving.' : "Mark {$missing} more students before saving.");
    }

    public static function unknownStudent(): self
    {
        return new self('The register has a student who is not in this class on that day. Reload the page and try again.');
    }

    public static function reasonRequired(): self
    {
        return new self('Add a short reason (at least 5 characters) for a change to an earlier register.');
    }

    public static function nothingChanged(): self
    {
        return new self('No changes to save.');
    }
}
