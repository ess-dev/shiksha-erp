<?php

namespace App\Domain\Fees\Enums;

use Spatie\TypeScriptTransformer\Attributes\TypeScript;

#[TypeScript]
enum PaymentMode: string
{
    case Cash = 'cash';
    case Cheque = 'cheque';
    case Upi = 'upi';
    case Card = 'card';
    case NetBanking = 'net_banking';
    case BankTransfer = 'bank_transfer';

    public function label(): string
    {
        return match ($this) {
            self::Cash => 'Cash',
            self::Cheque => 'Cheque',
            self::Upi => 'UPI',
            self::Card => 'Card',
            self::NetBanking => 'Net banking',
            self::BankTransfer => 'Bank transfer',
        };
    }

    /** The modes a person can pick at the counter (net banking exists only online). */
    public function isCounterMode(): bool
    {
        return $this !== self::NetBanking;
    }

    /** Prefix of the demo references for this mode. */
    public function referencePrefix(): string
    {
        return match ($this) {
            self::Cash => '',
            self::Cheque => 'CHQ',
            self::Upi => 'UPI',
            self::Card => 'CRD',
            self::NetBanking => 'NB',
            self::BankTransfer => 'TRF',
        };
    }
}
