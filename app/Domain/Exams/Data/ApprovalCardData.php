<?php

namespace App\Domain\Exams\Data;

use App\Domain\Exams\Enums\ReportCardKind;
use App\Domain\Exams\Enums\ReportCardStatus;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * One student's report card in an approver's list ("Om Joshi · Generated from
 * marks · 14 Oct").
 */
#[TypeScript]
class ApprovalCardData extends Data
{
    public function __construct(
        public string $ulid,
        public string $student_ulid,
        public string $student_name,
        public string $admission_number,
        public ?int $roll_number,
        public ReportCardKind $kind,
        public ReportCardStatus $status,
        public string $prepared_on,
        public ?string $file_name,
        public ?string $return_note,
    ) {}
}
