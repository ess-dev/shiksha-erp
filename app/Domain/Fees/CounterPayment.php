<?php

namespace App\Domain\Fees;

use App\Domain\Fees\Enums\PaymentMode;

/**
 * What the accountant typed at the counter. Amounts are paise; `amountPaise`
 * null means "everything selected".
 */
final readonly class CounterPayment
{
    /**
     * @param  list<string>  $invoiceUlids
     */
    public function __construct(
        public string $studentUlid,
        public array $invoiceUlids,
        public ?int $amountPaise,
        public PaymentMode $mode,
        public ?string $reference,
        public ?string $chequeNumber,
        public ?string $bank,
        public ?string $chequeDate,
        public ?string $note,
        public ?string $receivedFrom,
        public string $idempotencyKey,
    ) {}
}
