<?php

namespace App\Domain\Fees\Data;

use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * A student found for "New refund request", with what can still be refunded.
 */
#[TypeScript]
class RefundCandidateData extends Data
{
    /**
     * @param  list<RefundCandidatePaymentData>  $payments  Newest first, at most ten
     */
    public function __construct(
        public string $ulid,
        public string $name,
        public string $section_label,
        public string $admission_number,
        public int $refundable_paise,
        public array $payments,
    ) {}
}
