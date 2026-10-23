<?php

namespace App\Domain\School\Data;

use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

#[TypeScript]
class ScopeOptionsData extends Data
{
    /**
     * @param  list<CampusOptionData>  $campuses
     * @param  list<SectionOptionData>  $sections
     * @param  list<ClassLevelOptionData>  $levels
     */
    public function __construct(
        public array $campuses,
        public array $sections,
        public array $levels,
    ) {}
}
