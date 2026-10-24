<?php

namespace App\Domain\Exams\Data;

use App\Domain\Exams\Enums\ExamStatus;
use App\Domain\Exams\Enums\MarkSheetState;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * The marks of one subject in one section for an exam, with the students on
 * the section's roster today in roll-number order.
 */
#[TypeScript]
class MarksSheetData extends Data
{
    /**
     * @param  string|null  $teacher_name  Who enters these marks (for the view-only strip)
     * @param  string|null  $published_on  School-calendar day the marks were published
     * @param  list<MarkRowData>  $rows
     */
    public function __construct(
        public string $exam_ulid,
        public string $section_ulid,
        public string $subject_ulid,
        public string $exam_name,
        public ExamStatus $exam_status,
        public string $section_label,
        public string $subject_name,
        public int $max_marks,
        public MarkSheetState $state,
        public ?string $teacher_name,
        public ?string $published_on,
        public ?string $published_by,
        public ?string $reopened_by,
        public ?string $reopen_reason,
        public bool $can_reopen,
        public array $rows,
    ) {}
}
