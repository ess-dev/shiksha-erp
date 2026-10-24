<?php

namespace App\Domain\Exams\Data;

use App\Domain\Exams\Enums\ReportCardKind;
use App\Domain\Exams\Enums\ReportCardStatus;
use App\Domain\Students\Data\ReportCardData;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * An existing report card opened from a list: the generated card as it was
 * sent, or a short-lived link to the uploaded scan.
 */
#[TypeScript]
class ReportCardViewData extends Data
{
    public function __construct(
        public string $ulid,
        public ReportCardKind $kind,
        public ReportCardStatus $status,
        public string $exam_name,
        public string $session_name,
        public string $prepared_by,
        public string $prepared_on,
        public ?ReportCardData $card,
        public ?string $file_url,
        public ?string $file_name,
        public bool $is_image,
    ) {}
}
