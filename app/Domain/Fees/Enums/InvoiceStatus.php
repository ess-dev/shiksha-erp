<?php

namespace App\Domain\Fees\Enums;

use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * Worked out from the balance and the due date, never stored.
 */
#[TypeScript]
enum InvoiceStatus: string
{
    case Paid = 'paid';
    case Overdue = 'overdue';
    case PartialOverdue = 'partial_overdue';
    case Partial = 'partial';
    case Due = 'due';
    case Upcoming = 'upcoming';

    /** How many days ahead an unpaid installment counts as "Due" rather than "Upcoming". */
    public const DUE_SOON_DAYS = 30;

    public function label(): string
    {
        return match ($this) {
            self::Paid => 'Paid',
            self::Overdue => 'Overdue',
            self::PartialOverdue => 'Partly paid · overdue',
            self::Partial => 'Partly paid',
            self::Due => 'Due',
            self::Upcoming => 'Upcoming',
        };
    }

    public function isOverdue(): bool
    {
        return $this === self::Overdue || $this === self::PartialOverdue;
    }
}
