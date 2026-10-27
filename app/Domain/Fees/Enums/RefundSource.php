<?php

namespace App\Domain\Fees\Enums;

use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * Who asked: a parent (their request form is not built yet), the school
 * office, or the system itself for a duplicate payment.
 */
#[TypeScript]
enum RefundSource: string
{
    case Parent = 'parent';
    case Accounts = 'accounts';
    case System = 'system';

    public function label(): string
    {
        return match ($this) {
            self::Parent => 'Parent',
            self::Accounts => 'Accounts',
            self::System => 'System',
        };
    }
}
