<?php

namespace App\Domain\Attendance\Enums;

use Spatie\TypeScriptTransformer\Attributes\TypeScript;

#[TypeScript]
enum AttendanceStatus: string
{
    case Present = 'present';
    case Absent = 'absent';
    case Late = 'late';

    /** Late counts as present for the attendance percentage, as in the prototype. */
    public function countsAsPresent(): bool
    {
        return $this !== self::Absent;
    }
}
