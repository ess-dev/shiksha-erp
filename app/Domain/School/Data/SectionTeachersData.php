<?php

namespace App\Domain\School\Data;

use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * The "Subject teachers" tab for the chosen section.
 */
#[TypeScript]
class SectionTeachersData extends Data
{
    /**
     * @param  list<SubjectTeacherRowData>  $rows
     */
    public function __construct(
        public string $ulid,
        public string $name,
        public ?string $class_teacher_name,
        public bool $can_change,
        public array $rows,
    ) {}
}
