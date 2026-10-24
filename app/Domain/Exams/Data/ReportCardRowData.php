<?php

namespace App\Domain\Exams\Data;

use App\Domain\Exams\Enums\MarksReadiness;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * One student on the class teacher's report cards list.
 */
#[TypeScript]
class ReportCardRowData extends Data
{
    public function __construct(
        public string $student_ulid,
        public ?int $roll_number,
        public string $name,
        public MarksReadiness $marks,
        /** @var list<string> */
        public array $missing_subjects,
        public ?ReportCardSummaryData $card,
    ) {}
}
