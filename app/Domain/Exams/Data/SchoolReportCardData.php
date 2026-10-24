<?php

namespace App\Domain\Exams\Data;

use App\Domain\Exams\Enums\ReportCardKind;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * A published report card from the class teacher, in the parent's "Report
 * cards from school" list. `view_url` opens it: the generated card's page, or
 * a short-lived link to the uploaded scan.
 */
#[TypeScript]
class SchoolReportCardData extends Data
{
    public function __construct(
        public string $ulid,
        public string $exam_name,
        public ReportCardKind $kind,
        public string $prepared_by,
        public string $prepared_on,
        public string $view_url,
    ) {}
}
