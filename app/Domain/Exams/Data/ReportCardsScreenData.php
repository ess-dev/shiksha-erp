<?php

namespace App\Domain\Exams\Data;

use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * The class teacher's Report cards screen for one section and exam.
 * `approvers` names who approves this class's report cards ("Rachna Kapoor
 * (Coordinator)"), null when approval is off or nobody holds it.
 */
#[TypeScript]
class ReportCardsScreenData extends Data
{
    /**
     * @param  list<MarksSectionOptionData>  $sections  The person's classes
     * @param  list<MarksExamOptionData>  $exams  Exams of the chosen class
     * @param  list<ReportCardRowData>  $rows  Students on the roster today, by roll number
     */
    public function __construct(
        public array $sections,
        public ?string $section_ulid,
        public array $exams,
        public ?string $exam_ulid,
        public ?string $exam_name,
        public bool $approval_required,
        public ?string $approvers,
        public int $not_started_count,
        public int $sent_back_count,
        public int $awaiting_count,
        public int $published_count,
        public array $rows,
    ) {}
}
