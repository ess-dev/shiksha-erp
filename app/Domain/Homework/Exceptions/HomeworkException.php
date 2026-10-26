<?php

namespace App\Domain\Homework\Exceptions;

use DomainException;

/**
 * A homework change the rules do not allow. Code 403 is a refusal, 404 an
 * assignment, section or student outside the person's reach, anything else a
 * message shown on the homework screen.
 */
class HomeworkException extends DomainException
{
    public static function notAllowed(): self
    {
        return new self('You are not allowed to do that with this homework.', 403);
    }

    public static function notFound(): self
    {
        return new self('That homework was not found.', 404);
    }

    public static function titleNeeded(): self
    {
        return new self('Give it a short title (3 to 80 characters).');
    }

    public static function instructionsTooLong(int $max): self
    {
        return new self("Instructions can be at most {$max} characters.");
    }

    public static function dueDateNotAllowed(string $reason): self
    {
        return new self($reason);
    }

    public static function badFile(string $name): self
    {
        return new self("{$name} cannot be attached. Use a photo, PDF, Word, PowerPoint, Excel or text file of at most 10 MB.");
    }

    public static function tooManyFiles(int $max): self
    {
        return new self("You can attach up to {$max} files.");
    }

    public static function typeLocked(): self
    {
        return new self('The way of handing in cannot change once students have handed in or been checked.');
    }

    public static function feedbackTooLong(int $max): self
    {
        return new self("Feedback can be at most {$max} characters.");
    }

    public static function nothingToSubmit(): self
    {
        return new self('Choose at least one file to upload.');
    }

    public static function notUploadType(): self
    {
        return new self('This homework is done in the notebook, so nothing is uploaded.');
    }

    public static function alreadySubmitted(): self
    {
        return new self('This homework is already handed in.');
    }

    public static function archived(): self
    {
        return new self('Homework from an earlier session can only be viewed.');
    }

    public static function fileMissing(): self
    {
        return new self('The file is missing.', 404);
    }
}
