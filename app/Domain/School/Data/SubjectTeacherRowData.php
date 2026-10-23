<?php

namespace App\Domain\School\Data;

use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * One subject of a section and who teaches it. A null `teacher_ulid` means
 * "Automatic": the class teacher, named in `automatic_name` when there is one.
 */
#[TypeScript]
class SubjectTeacherRowData extends Data
{
    public function __construct(
        public string $subject_ulid,
        public string $subject_name,
        public ?string $teacher_ulid,
        public ?string $automatic_name,
    ) {}
}
