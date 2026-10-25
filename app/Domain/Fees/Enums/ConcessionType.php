<?php

namespace App\Domain\Fees\Enums;

use Spatie\TypeScriptTransformer\Attributes\TypeScript;

#[TypeScript]
enum ConcessionType: string
{
    case Percent = 'percent';
    case Flat = 'flat';
}
