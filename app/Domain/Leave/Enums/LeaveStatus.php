<?php

namespace App\Domain\Leave\Enums;

use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/** Where a leave request is: with the class teacher, with the coordinator, or settled. */
#[TypeScript]
enum LeaveStatus: string
{
    case Pending = 'pending';
    case Recommended = 'recommended';
    case Approved = 'approved';
    case Rejected = 'rejected';
    case Cancelled = 'cancelled';

    public function isOpen(): bool
    {
        return $this === self::Pending || $this === self::Recommended;
    }
}
