<?php

namespace App\Domain\Fees\Data;

use App\Domain\Fees\Enums\InvoiceStatus;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * One unpaid installment on the Dues screen.
 */
#[TypeScript]
class DueRowData extends Data
{
    public function __construct(
        public string $ulid,
        public string $student_ulid,
        public string $student_name,
        public string $admission_number,
        public string $section_label,
        public string $campus_name,
        public string $installment_label,
        public string $due_on,
        public int $paid_paise,
        public int $balance_paise,
        public InvoiceStatus $status,
        public bool $payment_pending,
        public bool $reminded_today,
        public bool $can_collect,
        public bool $can_remind,
    ) {}
}
