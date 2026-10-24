<?php

namespace App\Domain\Exams;

use App\Domain\Exams\Data\GradeBandData;
use App\Domain\Exams\Exceptions\GradeScaleException;

/**
 * A school's grading scale: grades from the highest down, each with the lowest
 * whole percentage that earns it; the last grade starts at 0. A score earns
 * the highest grade whose minimum it reaches, comparing exact fractions (80%
 * is B1 when A2 starts at 81; 90.5% is still A2 when A1 starts at 91).
 * The lowest grade is the failing one; the pass mark is where the next one
 * starts.
 */
final readonly class GradeScale
{
    public const MIN_GRADES = 2;

    public const MAX_GRADES = 12;

    /**
     * @param  list<GradeBand>  $bands  Highest first
     */
    private function __construct(public array $bands) {}

    /**
     * The CBSE-style scale from the prototype. illustrative — verify against
     * current board guidance; each school can change it.
     */
    public static function default(): self
    {
        return new self([
            new GradeBand('A1', 91, 10),
            new GradeBand('A2', 81, 9),
            new GradeBand('B1', 71, 8),
            new GradeBand('B2', 61, 7),
            new GradeBand('C1', 51, 6),
            new GradeBand('C2', 41, 5),
            new GradeBand('D', 33, 4),
            new GradeBand('E', 0, 0),
        ]);
    }

    /**
     * Builds and checks a scale from rows as a form or the database holds them.
     *
     * @param  array<mixed>  $rows  list of {grade, min_percent, grade_point}
     *
     * @throws GradeScaleException when the rows do not make a valid scale
     */
    public static function fromRows(array $rows): self
    {
        $count = count($rows);

        if ($count < self::MIN_GRADES || $count > self::MAX_GRADES) {
            throw GradeScaleException::invalid('A grading scale needs '.self::MIN_GRADES.' to '.self::MAX_GRADES.' grades.');
        }

        $bands = [];
        $seen = [];
        $previousMin = 101;

        foreach (array_values($rows) as $row) {
            if (! is_array($row)) {
                throw GradeScaleException::invalid('Every grade needs a name, a starting percentage and a grade point.');
            }

            $grade = trim((string) ($row['grade'] ?? ''));
            $min = $row['min_percent'] ?? null;
            $point = $row['grade_point'] ?? null;

            if ($grade === '' || mb_strlen($grade) > 4) {
                throw GradeScaleException::invalid('Grade names are 1 to 4 characters.');
            }

            if (isset($seen[mb_strtolower($grade)])) {
                throw GradeScaleException::invalid("The grade {$grade} appears twice.");
            }

            if (! is_int($min) || $min < 0 || $min > 100 || ! is_int($point) || $point < 0 || $point > 100) {
                throw GradeScaleException::invalid("Grade {$grade} needs a whole starting percentage and grade point from 0 to 100.");
            }

            if ($min >= $previousMin) {
                throw GradeScaleException::invalid('Each grade must start below the one above it.');
            }

            $seen[mb_strtolower($grade)] = true;
            $previousMin = $min;
            $bands[] = new GradeBand($grade, $min, $point);
        }

        if ($previousMin !== 0) {
            throw GradeScaleException::invalid('The lowest grade must start at 0%.');
        }

        return new self($bands);
    }

    /**
     * A stored scale, or null when it is missing or no longer valid.
     */
    public static function fromStored(mixed $value): ?self
    {
        if (! is_array($value)) {
            return null;
        }

        try {
            return self::fromRows($value);
        } catch (GradeScaleException) {
            return null;
        }
    }

    /**
     * @return list<array{grade: string, min_percent: int, grade_point: int}>
     */
    public function toRows(): array
    {
        return array_map(fn (GradeBand $band) => [
            'grade' => $band->grade,
            'min_percent' => $band->minPercent,
            'grade_point' => $band->gradePoint,
        ], $this->bands);
    }

    /**
     * The grade for `$scored` out of `$outOf` (same unit, `$outOf` above 0).
     */
    public function bandFor(int $scored, int $outOf): GradeBand
    {
        foreach ($this->bands as $band) {
            if (100 * $scored >= $band->minPercent * $outOf) {
                return $band;
            }
        }

        return $this->lowest();
    }

    public function lowest(): GradeBand
    {
        return $this->bands[count($this->bands) - 1];
    }

    public function isLowest(GradeBand $band): bool
    {
        return $band->grade === $this->lowest()->grade;
    }

    /** Where the lowest (failing) grade ends: 33 on the default scale. */
    public function passPercent(): int
    {
        return $this->bands[count($this->bands) - 2]->minPercent;
    }

    /**
     * For the page, with each grade's upper bound (100 for the top one).
     *
     * @return list<GradeBandData>
     */
    public function data(): array
    {
        $rows = [];
        $upper = 100;

        foreach ($this->bands as $band) {
            $rows[] = new GradeBandData($band->grade, $band->minPercent, $upper, $band->gradePoint, $this->isLowest($band));
            $upper = $band->minPercent - 1;
        }

        return $rows;
    }

    public function equals(self $other): bool
    {
        return $this->toRows() === $other->toRows();
    }
}
