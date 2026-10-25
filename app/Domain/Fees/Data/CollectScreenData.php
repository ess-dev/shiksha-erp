<?php

namespace App\Domain\Fees\Data;

use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * The Collect fee screen: the search box's results (two or more letters), and
 * the chosen student with their open installments.
 */
#[TypeScript]
class CollectScreenData extends Data
{
    /**
     * @param  list<CollectSearchResultData>  $results
     * @param  list<FeeOptionData>  $modes
     */
    public function __construct(
        public string $search,
        public array $results,
        public ?CollectStudentData $student,
        public array $modes,
        public string $today,
        public bool $has_schedule,
    ) {}
}
