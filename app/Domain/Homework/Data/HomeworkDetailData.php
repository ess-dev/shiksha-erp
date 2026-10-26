<?php

namespace App\Domain\Homework\Data;

use App\Domain\Homework\Enums\SubmissionType;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * The selected assignment with every student of its section.
 *
 * @param  bool  $can_manage  The signed-in teacher set it (they may edit, delete, check and give feedback)
 */
#[TypeScript]
class HomeworkDetailData extends Data
{
    /**
     * @param  list<HomeworkFileData>  $attachments
     * @param  list<HomeworkStudentRowData>  $rows
     */
    public function __construct(
        public string $ulid,
        public string $title,
        public string $section_ulid,
        public string $section_label,
        public string $subject_ulid,
        public string $subject,
        public ?string $instructions,
        public string $assigned_on,
        public string $assigned_by,
        public string $due_on,
        public int $days_left,
        public SubmissionType $submission_type,
        public int $submitted,
        public int $total,
        public int $to_check,
        public bool $can_manage,
        public array $attachments,
        public array $rows,
    ) {}
}
