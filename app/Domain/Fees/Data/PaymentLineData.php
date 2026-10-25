<?php

namespace App\Domain\Fees\Data;

use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * What part of a payment went to which student's installment.
 */
#[TypeScript]
class PaymentLineData extends Data
{
    public function __construct(
        public string $student_name,
        public string $section_label,
        public string $installment_label,
        public int $amount_paise,
        public bool $fully_paid,
    ) {}
}
