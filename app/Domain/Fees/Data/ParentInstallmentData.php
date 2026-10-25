<?php

namespace App\Domain\Fees\Data;

use App\Domain\Fees\Enums\InvoiceStatus;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * One installment of a child's fees as the parent sees it.
 */
#[TypeScript]
class ParentInstallmentData extends Data
{
    /**
     * @param  list<FeeHeadData>  $heads
     */
    public function __construct(
        public string $ulid,
        public string $label,
        public string $due_on,
        public InvoiceStatus $status,
        public int $net_paise,
        public int $paid_paise,
        public int $balance_paise,
        public ?string $paid_on,
        public array $heads,
        public int $concession_paise,
        public ?string $concession_note,
        public bool $payment_pending,
    ) {}
}
