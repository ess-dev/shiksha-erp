<?php

namespace App\Domain\Admissions\Data;

use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * One application as the details dialog shows it. The phone is masked unless
 * the viewer may see sensitive contact details.
 */
#[TypeScript]
class ApplicationDetailData extends Data
{
    /**
     * @param  list<DocumentRowData>  $documents
     * @param  list<HistoryRowData>  $history
     */
    public function __construct(
        public string $ulid,
        public string $name,
        public string $stage,
        public string $stage_label,
        public ?string $next_stage,
        public ?string $next_stage_label,
        public ?string $decision,
        public ?string $decision_label,
        public bool $is_closed,
        public bool $is_locked,
        public string $class_name,
        public string $campus_name,
        public string $gender,
        public string $gender_label,
        public ?string $born_on,
        public ?string $blood_group,
        public ?string $address,
        public ?string $house_ulid,
        public string $parent_name,
        public string $parent_relation,
        public string $phone,
        public ?string $email,
        public string $source_label,
        public string $enquired_on,
        public ?string $notes,
        public bool $details_complete,
        public int $missing_documents,
        public ?string $student_ulid,
        public array $documents,
        public array $history,
    ) {}
}
