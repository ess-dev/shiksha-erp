<?php

namespace App\Domain\School\Data;

use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * The chosen subject in the chosen section at all three levels: the whole
 * class, this section and each student.
 */
#[TypeScript]
class SyllabusDetailData extends Data
{
    /**
     * @param  list<SyllabusStudentNoteData>  $students
     */
    public function __construct(
        public string $section_ulid,
        public string $subject_ulid,
        public string $section_name,
        public string $class_name,
        public string $subject_name,
        public int $section_count,
        public bool $can_edit_class,
        public SyllabusClassData $class_syllabus,
        public SyllabusProgressData $progress,
        public array $students,
    ) {}
}
