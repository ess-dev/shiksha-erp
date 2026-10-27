<?php

namespace App\Domain\Leave\Exceptions;

use DomainException;

/**
 * A leave change the rules do not allow. Code 403 is a refusal, 404 a request,
 * child or file outside the person's reach, anything else a message shown on
 * the leave screen.
 */
class LeaveException extends DomainException
{
    public static function notAllowed(): self
    {
        return new self('You are not allowed to do that with this leave request.', 403);
    }

    public static function notFound(): self
    {
        return new self('That leave request was not found.', 404);
    }

    public static function archived(): self
    {
        return new self('Leave can only be applied for in the current session.');
    }

    public static function invalidDate(): self
    {
        return new self('Pick valid dates.');
    }

    public static function tooEarly(string $from): self
    {
        return new self("Pick a date from {$from} onwards.");
    }

    public static function endBeforeStart(): self
    {
        return new self('The end date cannot be before the start.');
    }

    public static function outsideYear(): self
    {
        return new self('The dates must fall inside the current academic year.');
    }

    public static function noSchoolDays(): self
    {
        return new self('There is no school on these dates.');
    }

    public static function tooLong(int $days): self
    {
        return new self("For more than {$days} days, please meet the principal.");
    }

    public static function overlaps(string $from, string $to): self
    {
        return new self($from === $to ? "Overlaps your request for {$from}." : "Overlaps your request for {$from} to {$to}.");
    }

    public static function reasonTooShort(int $min): self
    {
        return new self("Add a short explanation (at least {$min} characters).");
    }

    public static function reasonTooLong(int $max): self
    {
        return new self("The explanation can be at most {$max} characters.");
    }

    public static function badAttachment(string $name): self
    {
        return new self("{$name} cannot be attached. Use a photo or PDF of at most 5 MB.");
    }

    public static function notOpen(): self
    {
        return new self('This request has already been settled.');
    }

    public static function noteNeeded(string $decision): self
    {
        return new self($decision === 'reject'
            ? 'Give the parent a reason (at least 5 characters).'
            : 'Add a line for the coordinator (at least 5 characters).');
    }

    public static function noteTooLong(int $max): self
    {
        return new self("The note can be at most {$max} characters.");
    }

    public static function unknownDecision(): self
    {
        return new self('Choose approve, reject or recommend.');
    }

    public static function invalidLimit(): self
    {
        return new self('Enter a whole number of school days from 1 to 30.');
    }

    public static function unknownType(): self
    {
        return new self('Choose a leave type.');
    }

    public static function staffReasonTooShort(int $min): self
    {
        return new self("Add a short reason (at least {$min} characters).");
    }

    public static function notEnoughLeave(string $label, string $left): self
    {
        return new self("Not enough {$label} left ({$left} days). Choose another type.");
    }

    public static function certificateNeeded(string $label, int $days): self
    {
        return new self("Attach a certificate or letter for {$label} of more than {$days} days.");
    }

    public static function coverNeeded(string $section): self
    {
        return new self("Choose who marks {$section}'s register while the class teacher is away.");
    }

    public static function coverInvalid(): self
    {
        return new self('Choose another staff member to mark the register: they must be active, not the applicant and not on leave on those days.');
    }

    public static function fileMissing(): self
    {
        return new self('The file is missing.', 404);
    }
}
