<?php

namespace App\Domain\Fees\Data;

use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * The Fee structure screen for the current academic year.
 */
#[TypeScript]
class FeeStructureData extends Data
{
    /**
     * @param  list<FeeClassRowData>  $classes
     * @param  list<FeeGroupData>  $groups
     * @param  list<InstallmentData>  $installments
     * @param  list<ConcessionData>  $concessions
     * @param  list<string>  $head_names  Fee heads a concession can apply to
     * @param  list<string>  $classes_without_group
     */
    public function __construct(
        public string $academic_year,
        public string $today,
        public bool $has_schedule,
        public array $classes,
        public array $groups,
        public array $installments,
        public array $concessions,
        public array $head_names,
        public array $classes_without_group,
        public bool $can_manage,
    ) {}
}
