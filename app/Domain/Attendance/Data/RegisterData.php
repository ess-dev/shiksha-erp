<?php

namespace App\Domain\Attendance\Data;

use App\Domain\Attendance\Enums\RegisterState;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * Everything the daily attendance screen shows for one class on one day.
 */
#[TypeScript]
class RegisterData extends Data
{
    /**
     * @param  list<SectionChoiceData>  $sections  The classes the person may open, their own class first
     * @param  list<RosterStudentData>  $students  Empty when nobody has marked the day yet and the person cannot
     */
    public function __construct(
        public array $sections,
        public ?string $section,
        public string $date,
        public string $today,
        public string $min_date,
        public string $timezone,
        public RegisterState $state,
        public ?string $closed_reason,
        public bool $can_edit,
        public bool $needs_reason,
        public int $age_days,
        public int $correction_window_days,
        public ?string $class_teacher_name,
        public ?SavedRegisterData $saved,
        public array $students,
        public bool $can_manage_settings,
    ) {}
}
