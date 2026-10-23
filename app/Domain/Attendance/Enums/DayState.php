<?php

namespace App\Domain\Attendance\Enums;

use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * What a calendar day looks like for one student: a mark from the register, or
 * why there is none (holiday, weekly day off, still to come, not marked yet).
 */
#[TypeScript]
enum DayState: string
{
    case Present = 'present';
    case Absent = 'absent';
    case Late = 'late';
    case Holiday = 'holiday';
    case Off = 'off';
    case Upcoming = 'upcoming';
    case Unmarked = 'unmarked';
}
