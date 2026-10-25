<?php

namespace App\Domain\Fees\Data;

use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * A child's fees for one academic session: installments, payments with
 * receipts and the totals. Failed payments that still leave something owed
 * show as alerts.
 */
#[TypeScript]
class ParentFeesData extends Data
{
    /**
     * @param  list<ParentInstallmentData>  $installments  In schedule order
     * @param  list<ParentPaymentData>  $payments  Newest first
     * @param  list<ParentPaymentData>  $failed_alerts
     */
    public function __construct(
        public string $session_name,
        public bool $is_current_session,
        public string $child_name,
        public string $child_first_name,
        public string $section_label,
        public string $today,
        public bool $has_fees,
        public array $installments,
        public array $payments,
        public array $failed_alerts,
        public int $total_paid_paise,
        public int $total_due_paise,
        public int $total_billed_paise,
    ) {}
}
