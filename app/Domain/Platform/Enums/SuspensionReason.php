<?php

namespace App\Domain\Platform\Enums;

/**
 * Why a school was suspended. The wording is shown to the school; the list
 * is illustrative — verify with the owner.
 */
enum SuspensionReason: string
{
    case InvoiceOverdue = 'invoice_overdue';
    case RequestedBySchool = 'requested_by_school';
    case TermsReview = 'terms_review';

    public function label(): string
    {
        return match ($this) {
            self::InvoiceOverdue => 'Invoice overdue by 60 days',
            self::RequestedBySchool => 'Requested by the school',
            self::TermsReview => 'Terms of service review',
        };
    }
}
