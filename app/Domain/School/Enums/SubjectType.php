<?php

namespace App\Domain\School\Enums;

enum SubjectType: string
{
    case Core = 'core';
    case Elective = 'elective';
    case CoScholastic = 'co_scholastic';

    public function label(): string
    {
        return match ($this) {
            self::Core => 'Core (examined)',
            self::Elective => 'Elective / language (examined)',
            self::CoScholastic => 'Co-scholastic (graded on activity, no marks)',
        };
    }

    /** Examined subjects appear in marks entry and report cards. */
    public function isExamined(): bool
    {
        return $this !== self::CoScholastic;
    }
}
