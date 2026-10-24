<?php

use App\Domain\Exams\Exceptions\GradeScaleException;
use App\Domain\Exams\GradeScale;
use App\Domain\Exams\MarkValue;
use App\Domain\Exams\Percent;

it('grades a score on the default scale by exact fractions', function (int $halfMarks, int $outOfHalves, string $grade, int $point) {
    $band = GradeScale::default()->bandFor($halfMarks, $outOfHalves);

    expect($band->grade)->toBe($grade)->and($band->gradePoint)->toBe($point);
})->with([
    '25 of 25' => [50, 50, 'A1', 10],
    '23 of 25 is 92%' => [46, 50, 'A1', 10],
    '90.5% stays A2' => [181, 200, 'A2', 9],
    '91% is A1' => [182, 200, 'A1', 10],
    '20 of 25 is 80%, B1' => [40, 50, 'B1', 8],
    '21 of 25 is 84%' => [42, 50, 'A2', 9],
    '10 of 25 is 40%, D' => [20, 50, 'D', 4],
    '33% is D' => [66, 200, 'D', 4],
    '32.5% is E' => [65, 200, 'E', 0],
    '0 marks' => [0, 50, 'E', 0],
    '146 of 160 overall' => [292, 320, 'A1', 10],
]);

it('rounds percentages half-up with integers only', function (int $scored, int $outOf, int $whole, int $tenths) {
    expect(Percent::whole($scored, $outOf))->toBe($whole)
        ->and(Percent::tenths($scored, $outOf))->toBe($tenths);
})->with([
    '20 of 25' => [40, 50, 80, 800],
    '146 of 160' => [292, 320, 91, 913],
    '12.5 of 40 is 31.25%' => [25, 80, 31, 313],
    '1 of 8 is 12.5%' => [2, 16, 13, 125],
    '2 of 3' => [4, 6, 67, 667],
]);

it('reads what a teacher types in a marks box', function (string $typed, ?string $stored, ?int $halfMarks) {
    $value = MarkValue::parse($typed, 25);

    if ($stored === null) {
        expect($value)->toBeNull();

        return;
    }

    expect($value?->text())->toBe($stored)->and($value?->halfMarks)->toBe($halfMarks);
})->with([
    'blank' => ['  ', '', null],
    'absent in lower case' => ['ab', 'AB', null],
    'whole marks' => ['20', '20', 40],
    'a half' => ['12.5', '12.5', 25],
    'trailing .0' => ['12.0', '12', 24],
    'the maximum' => ['25', '25', 50],
    'zero' => ['0', '0', 0],
    'over the maximum' => ['25.5', null, null],
    'a quarter' => ['12.25', null, null],
    'not halves' => ['12.3', null, null],
    'negative' => ['-1', null, null],
    'text' => ['abc', null, null],
]);

it('knows the pass mark and the failing grade', function () {
    $scale = GradeScale::default();

    expect($scale->passPercent())->toBe(33)
        ->and($scale->isLowest($scale->lowest()))->toBeTrue()
        ->and($scale->lowest()->grade)->toBe('E')
        ->and(array_map(fn ($band) => [$band->grade, $band->min_percent, $band->max_percent], array_slice($scale->data(), 0, 3)))
        ->toBe([['A1', 91, 100], ['A2', 81, 90], ['B1', 71, 80]]);
});

it('accepts a school\'s own scale and keeps it as rows', function () {
    $rows = [
        ['grade' => 'A', 'min_percent' => 75, 'grade_point' => 4],
        ['grade' => 'B', 'min_percent' => 50, 'grade_point' => 3],
        ['grade' => 'F', 'min_percent' => 0, 'grade_point' => 0],
    ];

    $scale = GradeScale::fromRows($rows);

    expect($scale->toRows())->toBe($rows)
        ->and($scale->bandFor(49, 100)->grade)->toBe('F')
        ->and($scale->passPercent())->toBe(50);
});

it('refuses a scale that does not make sense', function (array $rows, string $message) {
    expect(fn () => GradeScale::fromRows($rows))->toThrow(GradeScaleException::class, $message);
})->with([
    'one grade' => [[['grade' => 'A', 'min_percent' => 0, 'grade_point' => 1]], 'A grading scale needs 2 to 12 grades.'],
    'not descending' => [[
        ['grade' => 'A', 'min_percent' => 50, 'grade_point' => 2],
        ['grade' => 'B', 'min_percent' => 60, 'grade_point' => 1],
        ['grade' => 'C', 'min_percent' => 0, 'grade_point' => 0],
    ], 'Each grade must start below the one above it.'],
    'lowest not at 0' => [[
        ['grade' => 'A', 'min_percent' => 50, 'grade_point' => 2],
        ['grade' => 'B', 'min_percent' => 10, 'grade_point' => 1],
    ], 'The lowest grade must start at 0%.'],
    'duplicate name' => [[
        ['grade' => 'A', 'min_percent' => 50, 'grade_point' => 2],
        ['grade' => 'a', 'min_percent' => 0, 'grade_point' => 1],
    ], 'The grade a appears twice.'],
    'long name' => [[
        ['grade' => 'ABCDE', 'min_percent' => 50, 'grade_point' => 2],
        ['grade' => 'F', 'min_percent' => 0, 'grade_point' => 1],
    ], 'Grade names are 1 to 4 characters.'],
]);

it('treats a stored scale that is no longer valid as missing', function () {
    expect(GradeScale::fromStored([['grade' => 'A']]))->toBeNull()
        ->and(GradeScale::fromStored(null))->toBeNull()
        ->and(GradeScale::fromStored(GradeScale::default()->toRows())?->equals(GradeScale::default()))->toBeTrue();
});
