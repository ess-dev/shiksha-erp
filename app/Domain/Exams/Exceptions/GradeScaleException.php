<?php

namespace App\Domain\Exams\Exceptions;

use DomainException;

/**
 * A grading scale the rules do not allow. Code 403 is a refusal; anything else
 * is a message shown on the grading scale form.
 */
class GradeScaleException extends DomainException
{
    public static function notAllowed(): self
    {
        return new self('You are not allowed to change the grading scale.', 403);
    }

    public static function invalid(string $message): self
    {
        return new self($message);
    }
}
