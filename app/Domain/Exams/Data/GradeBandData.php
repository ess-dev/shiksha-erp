<?php

namespace App\Domain\Exams\Data;

use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * One grade of the grading scale as pages show it: "B1 71–80%, grade point 8".
 * The page works out live grades from these bands; the server decides the
 * stored results.
 */
#[TypeScript]
class GradeBandData extends Data
{
    public function __construct(
        public string $grade,
        public int $min_percent,
        public int $max_percent,
        public int $grade_point,
        public bool $is_lowest,
    ) {}
}
