<?php

namespace App\Domain\Exams\Data;

use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * The report cards of one class and exam an approver can see, as one card on
 * the Approvals screen: "Class 7-A · Half-Yearly, sent by Neha Verma, 3 of 32
 * already published".
 */
#[TypeScript]
class ApprovalGroupData extends Data
{
    /**
     * @param  list<ApprovalCardData>  $cards  Students by roll number
     */
    public function __construct(
        public string $section_ulid,
        public string $section_label,
        public string $campus_name,
        public string $exam_ulid,
        public string $exam_name,
        public ?string $class_teacher_name,
        public int $awaiting_count,
        public int $published_count,
        public int $student_count,
        public array $cards,
    ) {}
}
