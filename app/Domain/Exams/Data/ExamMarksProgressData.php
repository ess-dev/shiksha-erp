<?php

namespace App\Domain\Exams\Data;

use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * How far marks entry has got for an exam, counted per (section, subject):
 * how many there are, how many have marks for every student enrolled today,
 * and how many parents can see.
 */
#[TypeScript]
class ExamMarksProgressData extends Data
{
    public function __construct(
        public int $sheets,
        public int $entered,
        public int $published,
    ) {}
}
