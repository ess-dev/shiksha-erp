<?php

namespace App\Domain\Students\Data;

use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

#[TypeScript]
class StudentOptionsData extends Data
{
    /**
     * @param  list<OptionData>  $genders
     * @param  list<OptionData>  $blood_groups
     * @param  list<OptionData>  $houses  Active houses; "Not assigned" is the empty value
     * @param  list<OptionData>  $transfer_reasons
     * @param  list<OptionData>  $document_kinds
     */
    public function __construct(
        public array $genders,
        public array $blood_groups,
        public array $houses,
        public array $transfer_reasons,
        public array $document_kinds,
    ) {}
}
