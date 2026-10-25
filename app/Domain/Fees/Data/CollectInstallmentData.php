<?php

namespace App\Domain\Fees\Data;

use App\Domain\Fees\Enums\InvoiceStatus;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * An unpaid installment offered at the counter.
 */
#[TypeScript]
class CollectInstallmentData extends Data
{
    public function __construct(
        public string $ulid,
        public string $label,
        public string $due_on,
        public InvoiceStatus $status,
        public int $net_paise,
        public int $paid_paise,
        public int $balance_paise,
        public ?string $concession_note,
        public ?string $pending_note,
    ) {}
}
