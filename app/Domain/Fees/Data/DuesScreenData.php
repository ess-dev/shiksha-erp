<?php

namespace App\Domain\Fees\Data;

use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * The Dues screen: unpaid installments of the current academic year with the
 * filters' options and what the person may do.
 */
#[TypeScript]
class DuesScreenData extends Data
{
    /**
     * @param  list<DueRowData>  $rows
     * @param  list<FeeOptionData>  $sections
     * @param  list<FeeOptionData>  $installments
     */
    public function __construct(
        public array $rows,
        public DuesSummaryData $summary,
        public int $total,
        public int $from,
        public int $to,
        public int $current_page,
        public int $last_page,
        public array $sections,
        public array $installments,
        public string $academic_year,
        public string $today,
        public bool $has_schedule,
        public bool $can_export,
    ) {}
}
