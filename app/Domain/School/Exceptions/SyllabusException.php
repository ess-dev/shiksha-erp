<?php

namespace App\Domain\School\Exceptions;

use DomainException;

/**
 * A syllabus, progress or note change that the rules do not allow. Code 403
 * is a refusal; 404 is a section or subject outside the person's reach.
 */
class SyllabusException extends DomainException
{
    public static function notAllowed(): self
    {
        return new self('You are not allowed to change this subject.', 403);
    }

    public static function notFound(): self
    {
        return new self('Subject not found.', 404);
    }

    public static function needsChapter(): self
    {
        return new self('Add at least one chapter.');
    }

    public static function tooManyChapters(int $max): self
    {
        return new self("A subject can have at most {$max} chapters.");
    }

    public static function chapterTooLong(int $max): self
    {
        return new self("A chapter name can be at most {$max} characters.");
    }

    public static function noteTooLong(int $max): self
    {
        return new self("A note can be at most {$max} characters.");
    }

    public static function noChaptersYet(): self
    {
        return new self('Ask for the chapter list to be added for the whole class first.');
    }

    public static function unknownChapter(): self
    {
        return new self('That chapter is not in the list.');
    }
}
