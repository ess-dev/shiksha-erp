<?php

namespace App\Domain\Exams\Data;

use App\Domain\Exams\Enums\ExamStatus;
use App\Domain\Exams\Enums\ExamType;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * One exam in the list, with what the person may do to it.
 */
#[TypeScript]
class ExamRowData extends Data
{
    /**
     * @param  list<string>  $class_ulids  Empty when it applies to all classes
     * @param  list<string>  $campus_ulids  Empty when it applies to all campuses
     * @param  list<ExamDateSheetRowData>  $date_sheet  Earliest first
     * @param  ExamMarksProgressData|null  $marks_progress  Only once marking has opened
     */
    public function __construct(
        public string $ulid,
        public string $name,
        public ExamType $type,
        public string $type_label,
        public ?string $created_by,
        public string $starts_on,
        public string $ends_on,
        public ExamStatus $status,
        public int $max_marks,
        public bool $max_marks_locked,
        public ?string $instructions,
        public bool $show_on_calendar,
        public bool $all_classes,
        public array $class_ulids,
        public bool $all_campuses,
        public array $campus_ulids,
        public string $classes_label,
        public array $date_sheet,
        public bool $can_change,
        public bool $can_publish,
        public bool $can_delete,
        public ?ExamMarksProgressData $marks_progress = null,
    ) {}
}
