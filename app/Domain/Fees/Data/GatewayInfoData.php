<?php

namespace App\Domain\Fees\Data;

use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * The payment gateway chosen for the school. `simulated` gateways let the
 * accountant pick what the "bank" reports, so flows can be tried locally.
 */
#[TypeScript]
class GatewayInfoData extends Data
{
    public function __construct(
        public string $label,
        public bool $simulated,
    ) {}
}
