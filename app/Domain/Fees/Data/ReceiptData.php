<?php

namespace App\Domain\Fees\Data;

use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * A fee receipt as it is printed. Only confirmed payments have one.
 */
#[TypeScript]
class ReceiptData extends Data
{
    /**
     * @param  list<PaymentLineData>  $lines
     */
    public function __construct(
        public string $receipt_number,
        public string $school_name,
        public ?string $school_address,
        public string $paid_at,
        public ?string $received_from,
        public string $mode_label,
        public bool $is_counter,
        public ?string $reference,
        public ?string $collected_by,
        public string $academic_year,
        public array $lines,
        public int $total_paise,
        public string $amount_in_words,
        public bool $is_archived,
    ) {}
}
