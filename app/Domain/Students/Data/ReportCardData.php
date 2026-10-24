<?php

namespace App\Domain\Students\Data;

use App\Domain\Attendance\Data\StudentTallyData;
use App\Domain\Exams\Data\StudentExamResultData;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * The printable report card for one child and one exam, generated from the
 * published marks. `attendance` covers the session up to the exam's last day
 * (null when Attendance is not in the school's plan).
 */
#[TypeScript]
class ReportCardData extends Data
{
    public function __construct(
        public string $school_name,
        public ?string $board,
        public string $campus_name,
        public ?string $campus_address,
        public string $session_name,
        public string $student_name,
        public string $admission_number,
        public string $born_on,
        public string $class_label,
        public ?string $class_teacher_name,
        public ?StudentTallyData $attendance,
        public StudentExamResultData $result,
    ) {}
}
