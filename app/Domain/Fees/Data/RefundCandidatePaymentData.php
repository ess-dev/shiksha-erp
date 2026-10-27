<?php

namespace App\Domain\Fees\Data;

use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * A confirmed payment a refund can be raised against.
 */
#[TypeScript]
class RefundCandidatePaymentData extends Data
{
    public function __construct(
        public string $ulid,
        public ?string $receipt_number,
        public string $mode_label,
        public string $paid_on,
        public int $amount_paise,
        public int $refundable_paise,
    ) {}
}
