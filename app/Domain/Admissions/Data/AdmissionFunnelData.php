<?php

namespace App\Domain\Admissions\Data;

use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * Admissions on the staff dashboard. Each stage counts every application that
 * reached at least that stage (including ones later marked "Not admitted").
 * `conversion_percent` is enrolled out of all enquiries, whole percent, half-up.
 */
#[TypeScript]
class AdmissionFunnelData extends Data
{
    /**
     * @param  list<StageData>  $stages
     */
    public function __construct(
        public int $open_applications,
        public int $new_this_week,
        public int $conversion_percent,
        public array $stages,
    ) {}
}
