<?php

namespace App\Domain\Exams\Data;

use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * The marks entry screen: the pickers for the current choice and the sheet.
 * `sections` are those the chosen exam is for; `subjects` belong to the chosen
 * section. `scale` is the grading scale the chosen exam uses.
 */
#[TypeScript]
class MarksScreenData extends Data
{
    /**
     * @param  list<MarksExamOptionData>  $exams
     * @param  list<MarksSectionOptionData>  $sections
     * @param  list<MarksSubjectOptionData>  $subjects
     * @param  list<GradeBandData>  $scale
     */
    public function __construct(
        public array $exams,
        public array $sections,
        public array $subjects,
        public ?MarksSheetData $sheet,
        public array $scale,
        public int $pass_percent,
        public bool $can_change_scale,
    ) {}
}
