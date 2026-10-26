<?php

namespace App\Domain\Homework\Data;

use App\Domain\Homework\Enums\SubmissionType;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * One assignment in the teacher's list.
 *
 * @param  string  $due_on  Calendar date
 * @param  int  $days_left  Days from today to the due date (negative once it has passed)
 * @param  int  $to_check  Uploaded work with no feedback and not yet checked
 */
#[TypeScript]
class HomeworkListItemData extends Data
{
    public function __construct(
        public string $ulid,
        public string $title,
        public string $section_label,
        public string $subject,
        public string $due_on,
        public int $days_left,
        public SubmissionType $submission_type,
        public int $submitted,
        public int $total,
        public int $to_check,
    ) {}
}
