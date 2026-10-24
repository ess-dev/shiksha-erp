<?php

namespace App\Domain\Exams\Data;

use App\Domain\Exams\Enums\ReportCardKind;
use App\Domain\Exams\Enums\ReportCardStatus;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * A student's report card for the chosen exam, as the class teacher's list
 * shows it ("Published · Generated · 3 Oct").
 *
 * @param  string  $prepared_on  School-calendar day it was generated or uploaded
 */
#[TypeScript]
class ReportCardSummaryData extends Data
{
    public function __construct(
        public string $ulid,
        public ReportCardKind $kind,
        public ReportCardStatus $status,
        public string $prepared_on,
        public ?string $file_name,
        public ?string $return_note,
    ) {}
}
