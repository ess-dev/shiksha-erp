<?php

namespace App\Domain\Fees\Data;

use App\Domain\Fees\Enums\PaymentStatus;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * One payment in the Payments list, with the actions that make sense for it
 * and what the person may do.
 */
#[TypeScript]
class PaymentRowData extends Data
{
    public function __construct(
        public string $ulid,
        public string $paid_at,
        public string $student_name,
        public string $section_label,
        public ?string $receipt_number,
        public string $mode_label,
        public bool $is_counter,
        public ?string $reference,
        public int $amount_paise,
        public PaymentStatus $status,
        public string $status_note,
        public bool $can_check,
        public bool $can_clear_cheque,
        public bool $can_recheck,
        public bool $can_send_link,
        public bool $link_sent,
        public ?ReceiptData $receipt,
        public PaymentDetailData $detail,
    ) {}
}
