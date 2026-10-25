<?php

namespace App\Domain\Fees\Data;

use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * Totals over every installment the Dues filters match (not just the page).
 */
#[TypeScript]
class DuesSummaryData extends Data
{
    public function __construct(
        public int $installments,
        public int $students,
        public int $balance_paise,
        public int $overdue_paise,
    ) {}
}
