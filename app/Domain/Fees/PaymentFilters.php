<?php

namespace App\Domain\Fees;

/**
 * What the Payments screen is filtered by. `status` is all, pending, failed
 * or success; `mode` all or a PaymentMode value; dates are calendar dates in
 * the school's time zone.
 */
final readonly class PaymentFilters
{
    public function __construct(
        public string $search = '',
        public string $status = 'all',
        public string $mode = 'all',
        public ?string $from = null,
        public ?string $to = null,
        public ?string $campusUlid = null,
    ) {}
}
