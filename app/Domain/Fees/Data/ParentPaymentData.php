<?php

namespace App\Domain\Fees\Data;

use App\Domain\Fees\Enums\PaymentStatus;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * One payment for a child, as the parent sees it: a receipt for a confirmed one.
 */
#[TypeScript]
class ParentPaymentData extends Data
{
    public function __construct(
        public string $ulid,
        public int $amount_paise,
        public string $paid_at,
        public string $mode_label,
        public PaymentStatus $status,
        public ?string $receipt_number,
        public string $for_label,
        public ?string $note,
        public ?ReceiptData $receipt,
    ) {}
}
