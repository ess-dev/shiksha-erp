<?php

namespace App\Domain\Exams;

/**
 * One grade of a grading scale: the grade name, the lowest whole percentage
 * that earns it and its grade point.
 */
final readonly class GradeBand
{
    public function __construct(
        public string $grade,
        public int $minPercent,
        public int $gradePoint,
    ) {}
}
