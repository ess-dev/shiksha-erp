<?php

namespace App\Domain\Exams\Data;

use App\Domain\Exams\Enums\ReportCardStatus;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * Report cards an approver may decide on, grouped by class and exam. `status`
 * is the filter in use (null shows every status); the counts are over all
 * the approver's classes, whatever the filter.
 */
#[TypeScript]
class ReportCardApprovalsData extends Data
{
    /**
     * @param  list<MarksSectionOptionData>  $sections  Classes the person approves for
     * @param  list<ApprovalGroupData>  $groups
     */
    public function __construct(
        public ?ReportCardStatus $status,
        public array $sections,
        public ?string $section_ulid,
        public int $awaiting_count,
        public int $published_count,
        public int $sent_back_count,
        public array $groups,
    ) {}
}
