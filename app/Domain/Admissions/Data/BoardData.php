<?php

namespace App\Domain\Admissions\Data;

use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

#[TypeScript]
class BoardData extends Data
{
    /**
     * @param  list<StageData>  $stages
     * @param  list<ApplicationCardData>  $cards  Newest enquiry first
     */
    public function __construct(
        public BoardSummaryData $summary,
        public array $stages,
        public array $cards,
    ) {}
}
