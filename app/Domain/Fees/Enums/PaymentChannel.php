<?php

namespace App\Domain\Fees\Enums;

use Spatie\TypeScriptTransformer\Attributes\TypeScript;

#[TypeScript]
enum PaymentChannel: string
{
    case Counter = 'counter';
    case Online = 'online';
}
