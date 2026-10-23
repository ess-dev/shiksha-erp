<?php

namespace App\Domain\Admissions\Data;

use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * What the "Enrolled" confirmation shows after an applicant becomes a student.
 */
#[TypeScript]
class EnrolledApplicantData extends Data
{
    public function __construct(
        public string $student_ulid,
        public string $name,
        public string $section_label,
        public string $admission_number,
        public int $roll_number,
    ) {}
}
