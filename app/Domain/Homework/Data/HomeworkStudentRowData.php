<?php

namespace App\Domain\Homework\Data;

use App\Domain\Homework\Enums\StudentHomeworkStatus;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * One student on an assignment: where they stand, what they uploaded, whether
 * the teacher checked it and the feedback parents read.
 *
 * @param  string|null  $submitted_on  Calendar date of the upload, or of the check for notebook work
 */
#[TypeScript]
class HomeworkStudentRowData extends Data
{
    /**
     * @param  list<HomeworkFileData>  $files
     */
    public function __construct(
        public string $student_ulid,
        public ?int $roll_number,
        public string $name,
        public StudentHomeworkStatus $status,
        public ?string $submitted_on,
        public bool $is_checked,
        public ?string $feedback,
        public array $files,
    ) {}
}
