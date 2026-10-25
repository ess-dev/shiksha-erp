<?php

namespace App\Domain\Fees\Data;

use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * One installment of the schedule; billed once its due date has passed.
 */
#[TypeScript]
class InstallmentData extends Data
{
    public function __construct(
        public string $ulid,
        public string $label,
        public string $due_on,
        public bool $is_billed,
    ) {}
}
