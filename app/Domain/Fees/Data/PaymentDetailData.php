<?php

namespace App\Domain\Fees\Data;

use App\Domain\Fees\Enums\PaymentStatus;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * Everything the "Payment details" dialog shows about one payment.
 */
#[TypeScript]
class PaymentDetailData extends Data
{
    /**
     * @param  list<PaymentLineData>  $lines
     */
    public function __construct(
        public string $payment_id,
        public PaymentStatus $status,
        public string $mode_label,
        public bool $is_counter,
        public ?string $gateway,
        public ?string $reference,
        public ?string $receipt_number,
        public string $paid_at,
        public ?string $collected_by,
        public ?string $note,
        public ?string $failure_reason,
        public ?string $cheque_details,
        public int $excess_paise,
        public array $lines,
    ) {}
}
