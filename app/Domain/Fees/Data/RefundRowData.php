<?php

namespace App\Domain\Fees\Data;

use App\Domain\Fees\Enums\RefundMethod;
use App\Domain\Fees\Enums\RefundSource;
use App\Domain\Fees\Enums\RefundStatus;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * One refund request in the Refunds list.
 */
#[TypeScript]
class RefundRowData extends Data
{
    public function __construct(
        public string $ulid,
        public string $requested_on,
        public RefundSource $source,
        public string $student_name,
        public string $section_label,
        public string $admission_number,
        public string $reason,
        public ?string $receipt_number,
        public int $amount_paise,
        public RefundStatus $status,
        public ?RefundMethod $method,
        public ?string $reference,
        public ?string $decided_on,
        public ?string $reject_reason,
        public bool $needs_principal,
        public string $original_label,
    ) {}
}
