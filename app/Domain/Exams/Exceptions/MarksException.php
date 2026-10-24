<?php

namespace App\Domain\Exams\Exceptions;

use DomainException;

/**
 * A marks change the rules do not allow. Code 403 is a refusal, 404 an exam,
 * section, subject or student outside the person's reach, anything else a
 * message shown under the marks sheet.
 */
class MarksException extends DomainException
{
    public static function notAllowed(): self
    {
        return new self('You can only view these marks.', 403);
    }

    public static function notFound(): self
    {
        return new self('Those marks were not found.', 404);
    }

    public static function markingClosed(): self
    {
        return new self('Marking for this exam is closed.');
    }

    public static function alreadyPublished(): self
    {
        return new self('These marks are published. Ask the principal to reopen them for a correction.');
    }

    public static function invalid(int $count, int $maxMarks): self
    {
        $marks = $count === 1 ? '1 mark' : "{$count} marks";

        return new self("Fix {$marks} first: use 0–{$maxMarks} (halves allowed) or AB.");
    }

    public static function incomplete(int $count): self
    {
        $students = $count === 1 ? '1 more student' : "{$count} more students";

        return new self("Enter marks (or AB) for {$students} before publishing.");
    }

    public static function nothingToPublish(): self
    {
        return new self('There are no students in this class to publish marks for.');
    }

    public static function notPublished(): self
    {
        return new self('These marks are not published, so there is nothing to reopen.');
    }

    public static function reasonNeeded(): self
    {
        return new self('Give a reason of 3 to 200 characters for reopening.');
    }
}
