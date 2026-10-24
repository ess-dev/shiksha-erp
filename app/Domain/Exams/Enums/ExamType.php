<?php

namespace App\Domain\Exams\Enums;

use Spatie\TypeScriptTransformer\Attributes\TypeScript;

#[TypeScript]
enum ExamType: string
{
    case UnitTest = 'unit_test';
    case PeriodicTest = 'periodic_test';
    case HalfYearly = 'half_yearly';
    case Annual = 'annual';
    case Practical = 'practical';
    case Other = 'other';

    /** Suggested maximum marks in the form; illustrative — verify with the school. */
    public function defaultMaxMarks(): int
    {
        return match ($this) {
            self::UnitTest, self::Other => 25,
            self::PeriodicTest => 40,
            self::HalfYearly, self::Annual => 80,
            self::Practical => 30,
        };
    }

    public function label(): string
    {
        return match ($this) {
            self::UnitTest => 'Unit test',
            self::PeriodicTest => 'Periodic test',
            self::HalfYearly => 'Half-yearly exam',
            self::Annual => 'Annual exam',
            self::Practical => 'Practical / project',
            self::Other => 'Other',
        };
    }
}
