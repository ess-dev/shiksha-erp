<?php

namespace App\Domain\Exams\Data;

use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * Everything the Exams and tests screen shows: the exams the person reaches
 * with what they may do to each, status counts, and the choices the form needs.
 */
#[TypeScript]
class ExamsOverviewData extends Data
{
    /**
     * @param  list<ExamRowData>  $exams  Earliest start first
     */
    public function __construct(
        public int $scheduled_count,
        public int $marking_count,
        public int $published_count,
        public int $cancelled_count,
        public array $exams,
        public ExamFormOptionsData $form,
    ) {}
}
