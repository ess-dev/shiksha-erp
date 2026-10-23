<?php

namespace App\Domain\Admissions\Data;

use App\Domain\Students\Data\OptionData;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * Choices for the Add enquiry and details forms. `offered` lists the
 * campus and class pairs the person may take enquiries for (the campus runs a
 * section of that class this year).
 */
#[TypeScript]
class AdmissionOptionsData extends Data
{
    /**
     * @param  list<OptionData>  $genders
     * @param  list<OptionData>  $blood_groups
     * @param  list<OptionData>  $houses
     * @param  list<OptionData>  $sources
     * @param  list<OptionData>  $relations
     * @param  list<OptionData>  $campuses
     * @param  list<ClassOptionData>  $classes
     * @param  list<AdmissionPlaceData>  $offered
     */
    public function __construct(
        public array $genders,
        public array $blood_groups,
        public array $houses,
        public array $sources,
        public array $relations,
        public array $campuses,
        public array $classes,
        public array $offered,
    ) {}
}
