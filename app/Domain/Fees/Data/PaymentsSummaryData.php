<?php

namespace App\Domain\Fees\Data;

use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * The four cards above the Payments list, over everything the person may see.
 */
#[TypeScript]
class PaymentsSummaryData extends Data
{
    public function __construct(
        public int $successful_paise,
        public int $successful_count,
        public int $pending_count,
        public int $pending_paise,
        public int $failed_count,
        public int $failed_paise,
    ) {}
}
