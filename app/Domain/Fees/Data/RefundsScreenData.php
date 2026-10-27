<?php

namespace App\Domain\Fees\Data;

use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * The Refunds screen: the counters above the list, the list for the chosen tab
 * and, while the person searches in "New refund request", the students found.
 */
#[TypeScript]
class RefundsScreenData extends Data
{
    /**
     * @param  list<RefundRowData>  $rows  Newest first, at most 100
     * @param  list<FeeOptionData>  $methods
     * @param  list<RefundCandidateData>  $candidates
     */
    public function __construct(
        public array $rows,
        public string $tab,
        public int $pending_count,
        public int $pending_paise,
        public int $approved_count,
        public int $approved_paise,
        public int $rejected_count,
        public int $limit_paise,
        public array $methods,
        public string $search,
        public array $candidates,
    ) {}
}
