<?php

namespace App\Domain\School\Data;

use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * The teacher's Subjects screen: the subjects the person may keep up to date
 * (the ones they teach first) and the chosen one in full.
 */
#[TypeScript]
class SyllabusScreenData extends Data
{
    /**
     * @param  list<SyllabusChoiceData>  $choices
     */
    public function __construct(
        public string $year_name,
        public array $choices,
        public ?SyllabusDetailData $selected,
    ) {}
}
