<?php

namespace App\Domain\Fees\Enums;

use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * How an approved refund reaches the family. `Adjustment` is the only one
 * that moves money inside the books: it is credited to unpaid installments.
 */
#[TypeScript]
enum RefundMethod: string
{
    case Original = 'original';
    case BankTransfer = 'bank_transfer';
    case Cheque = 'cheque';
    case Adjustment = 'adjustment';

    public function label(): string
    {
        return match ($this) {
            self::Original => 'Original payment method',
            self::BankTransfer => 'Bank transfer (NEFT)',
            self::Cheque => 'Cheque',
            self::Adjustment => 'Adjust against next term',
        };
    }

    /** Words for "Paid via …" on a decided refund. */
    public function paidVia(): string
    {
        return match ($this) {
            self::Original => 'original payment method',
            self::BankTransfer => 'bank transfer',
            self::Cheque => 'cheque',
            self::Adjustment => 'adjustment against installments',
        };
    }
}
