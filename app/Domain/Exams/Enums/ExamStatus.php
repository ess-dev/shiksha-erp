<?php

namespace App\Domain\Exams\Enums;

use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * Scheduled, then marking open, then results published; or cancelled.
 * Published is final. Cancelled can be restored to scheduled.
 */
#[TypeScript]
enum ExamStatus: string
{
    case Scheduled = 'scheduled';
    case Marking = 'marking';
    case Published = 'published';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::Scheduled => 'Scheduled',
            self::Marking => 'Marking open',
            self::Published => 'Results published',
            self::Cancelled => 'Cancelled',
        };
    }

    /** Whether an exam in this status may move to $next. */
    public function mayMoveTo(self $next): bool
    {
        return match ($this) {
            self::Scheduled => in_array($next, [self::Marking, self::Cancelled], true),
            self::Marking => in_array($next, [self::Scheduled, self::Published, self::Cancelled], true),
            self::Cancelled => $next === self::Scheduled,
            self::Published => false,
        };
    }

    /** Details may still be changed. */
    public function isEditable(): bool
    {
        return $this === self::Scheduled || $this === self::Marking;
    }
}
