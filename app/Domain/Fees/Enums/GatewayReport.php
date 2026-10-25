<?php

namespace App\Domain\Fees\Enums;

use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * What a payment gateway says about a payment: the money is with the school
 * (captured), definitely not taken (failed), or it cannot say yet.
 */
#[TypeScript]
enum GatewayReport: string
{
    case Captured = 'captured';
    case Failed = 'failed';
    case Pending = 'pending';
}
