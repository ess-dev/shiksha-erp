<?php

namespace App\Domain\School\Data;

use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * One entry of the "Class and subject" picker: a subject in a section.
 * `via_class_teacher` is true when the person may edit it only as the
 * section's class teacher (they are not its chosen subject teacher).
 */
#[TypeScript]
class SyllabusChoiceData extends Data
{
    public function __construct(
        public string $section_ulid,
        public string $subject_ulid,
        public string $section_name,
        public string $subject_name,
        public string $campus_name,
        public bool $via_class_teacher,
    ) {}
}
