<?php

namespace App\Domain\Admissions\Data;

use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

#[TypeScript]
class BoardSummaryData extends Data
{
    public function __construct(
        public int $open_applications,
        public int $new_this_week,
        public int $enrolled,
        public int $conversion_percent,
    ) {}
}
