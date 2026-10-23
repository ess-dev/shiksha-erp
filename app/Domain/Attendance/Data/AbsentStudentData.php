<?php

namespace App\Domain\Attendance\Data;

use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * A student marked absent today, for the dashboard. `guardian_phone` is masked
 * unless the viewer may see sensitive student details for that section.
 * `days_in_a_row` counts today and the school days just before it.
 */
#[TypeScript]
class AbsentStudentData extends Data
{
    public function __construct(
        public string $student_ulid,
        public string $name,
        public ?int $roll_number,
        public string $admission_number,
        public string $section_label,
        public string $campus_name,
        public ?string $guardian_name,
        public ?string $guardian_relation,
        public ?string $guardian_phone,
        public int $days_in_a_row,
        public ?string $marked_by,
        public string $marked_at,
    ) {}
}
