<?php

namespace App\Domain\Exams\Exceptions;

use DomainException;

/**
 * A report card change the rules do not allow. Code 403 is a refusal, 404 a
 * section, student or report card outside the person's reach, anything else a
 * message shown on the report cards screen.
 */
class ReportCardException extends DomainException
{
    public static function notAllowed(): self
    {
        return new self('You are not allowed to do that with these report cards.', 403);
    }

    public static function notFound(): self
    {
        return new self('That report card was not found.', 404);
    }

    public static function marksNotReady(string $name): self
    {
        return new self("{$name}’s marks are not all entered and published yet.");
    }

    public static function alreadyPrepared(string $name): self
    {
        return new self("{$name} already has a report card for this exam.");
    }

    public static function nothingToDo(): self
    {
        return new self('There are no report cards to do that with.');
    }

    public static function remarkTooLong(int $max): self
    {
        return new self("A remark can be at most {$max} characters.");
    }

    public static function noteNeeded(): self
    {
        return new self('Say what needs changing (3 to 200 characters).');
    }

    public static function badFile(): self
    {
        return new self('Upload a PDF, JPEG or PNG file of at most 5 MB.');
    }

    public static function fileMissing(): self
    {
        return new self('The file for this report card is missing.', 404);
    }
}
