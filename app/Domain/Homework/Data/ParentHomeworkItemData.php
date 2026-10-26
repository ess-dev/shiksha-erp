<?php

namespace App\Domain\Homework\Data;

use App\Domain\Homework\Enums\ParentHomeworkState;
use App\Domain\Homework\Enums\SubmissionType;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * One assignment as a parent sees it for their child.
 *
 * @param  bool  $is_late  Uploaded after the due date
 * @param  bool  $can_upload  Upload homework that is not handed in yet (late uploads included)
 */
#[TypeScript]
class ParentHomeworkItemData extends Data
{
    /**
     * @param  list<HomeworkFileData>  $attachments
     * @param  list<HomeworkFileData>  $my_files
     */
    public function __construct(
        public string $ulid,
        public string $subject,
        public string $teacher_name,
        public string $title,
        public ?string $instructions,
        public string $assigned_on,
        public string $due_on,
        public int $days_left,
        public SubmissionType $submission_type,
        public ParentHomeworkState $state,
        public bool $is_late,
        public ?string $feedback,
        public bool $can_upload,
        public array $attachments,
        public array $my_files,
    ) {}
}
