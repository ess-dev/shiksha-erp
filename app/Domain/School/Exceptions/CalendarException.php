<?php

namespace App\Domain\School\Exceptions;

use DomainException;

/**
 * A change to the school's working days, holidays or class teachers that the
 * rules do not allow.
 */
class CalendarException extends DomainException
{
    public static function notAllowed(): self
    {
        return new self('You are not allowed to change this.', 403);
    }

    public static function invalidWorkingDays(): self
    {
        return new self('Choose at least one working day of the week.');
    }

    public static function invalidWindow(): self
    {
        return new self('The correction window is a whole number of days from 0 to 60.');
    }

    public static function invalidStaffLeavePolicy(): self
    {
        return new self('Enter whole days: a yearly allowance from 1 to 365 (or leave it empty for "as needed") and a certificate limit from 0 to 30.');
    }

    public static function invalidLeaveLimit(): self
    {
        return new self('Enter a whole number of school days from 1 to 30.');
    }

    public static function invalidDate(): self
    {
        return new self('Enter a real date for the holiday.');
    }

    public static function duplicateHoliday(string $date): self
    {
        return new self("A holiday is already listed on {$date}.");
    }

    public static function invalidName(): self
    {
        return new self('Give the holiday a short name (up to 100 characters).');
    }

    public static function staffUnavailable(): self
    {
        return new self('Choose an active member of staff as class teacher.');
    }

    public static function sectionInactive(): self
    {
        return new self('Only an active section can have a class teacher.');
    }
}
