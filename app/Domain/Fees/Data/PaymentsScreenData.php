<?php

namespace App\Domain\Fees\Data;

use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * The Payments and reconciliation screen.
 */
#[TypeScript]
class PaymentsScreenData extends Data
{
    /**
     * @param  list<PaymentRowData>  $rows
     * @param  list<FeeOptionData>  $modes
     * @param  list<string>  $bounce_reasons  What a bank gives for a bounced cheque
     */
    public function __construct(
        public array $rows,
        public PaymentsSummaryData $summary,
        public int $total,
        public int $from,
        public int $to,
        public int $current_page,
        public int $last_page,
        public array $modes,
        public ?GatewayInfoData $gateway,
        public array $bounce_reasons,
        public string $today,
        public bool $can_export,
    ) {}
}
