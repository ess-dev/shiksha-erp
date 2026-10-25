<?php

namespace App\Domain\Fees\Enums;

use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * Pending: money not confirmed yet (an online payment awaiting the gateway, a
 * cheque awaiting clearance). Success: confirmed, receipt issued. Failed: no money taken.
 */
#[TypeScript]
enum PaymentStatus: string
{
    case Pending = 'pending';
    case Success = 'success';
    case Failed = 'failed';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Pending',
            self::Success => 'Success',
            self::Failed => 'Failed',
        };
    }
}
