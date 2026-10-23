<?php

use App\Domain\Access\Data\Scope;
use App\Domain\Access\Data\ScopeSet;
use App\Domain\Access\Data\ScopeTarget;

it('decides which records a scope covers', function (Scope $scope, ScopeTarget $target, bool $covers) {
    expect($scope->covers($target))->toBe($covers);
})->with([
    'school covers anything' => [Scope::school(), new ScopeTarget, true],
    'campus: record at that campus' => [Scope::campuses(['c1', 'c2']), new ScopeTarget(campusUlid: 'c2'), true],
    'campus: other campus' => [Scope::campuses(['c1']), new ScopeTarget(campusUlid: 'c9'), false],
    'campus: school-wide record' => [Scope::campuses(['c1']), new ScopeTarget, false],
    'wing: inside range' => [Scope::wing(['c1'], 1, 5), new ScopeTarget('c1', 5, 's1'), true],
    'wing: below range' => [Scope::wing(['c1'], 2, 5), new ScopeTarget('c1', 1, 's1'), false],
    'wing: above range' => [Scope::wing(['c1'], 1, 5), new ScopeTarget('c1', 6, 's1'), false],
    'wing: other campus' => [Scope::wing(['c1'], 1, 5), new ScopeTarget('c2', 3, 's1'), false],
    'wing: no class level' => [Scope::wing(['c1'], 1, 5), new ScopeTarget(campusUlid: 'c1'), false],
    'sections: listed' => [Scope::sections(['s1', 's2']), new ScopeTarget(sectionUlid: 's2'), true],
    'sections: not listed' => [Scope::sections(['s1']), new ScopeTarget(sectionUlid: 's3'), false],
    'sections: no section' => [Scope::sections(['s1']), new ScopeTarget(campusUlid: 'c1'), false],
]);

it('knows when one scope lies within another', function (Scope $outer, Scope $inner, bool $contains) {
    expect($outer->contains($inner))->toBe($contains);
})->with([
    'school contains all' => [Scope::school(), Scope::sections(['s1']), true],
    'campus contains its subset' => [Scope::campuses(['c1', 'c2']), Scope::campuses(['c1']), true],
    'campus does not contain a wider set' => [Scope::campuses(['c1']), Scope::campuses(['c1', 'c2']), false],
    'campus contains a wing at its campuses' => [Scope::campuses(['c1']), Scope::wing(['c1'], 1, 5), true],
    'campus cannot be shown to contain sections' => [Scope::campuses(['c1']), Scope::sections(['s1']), false],
    'wing contains a narrower wing' => [Scope::wing(['c1', 'c2'], 1, 8), Scope::wing(['c1'], 2, 5), true],
    'wing does not contain a wider range' => [Scope::wing(['c1'], 2, 5), Scope::wing(['c1'], 1, 5), false],
    'wing does not contain a campus' => [Scope::wing(['c1'], 1, 5), Scope::campuses(['c1']), false],
    'sections contain a subset' => [Scope::sections(['s1', 's2']), Scope::sections(['s2']), true],
    'sections do not contain others' => [Scope::sections(['s1']), Scope::sections(['s2']), false],
    'nothing but school contains school' => [Scope::campuses(['c1']), Scope::school(), false],
]);

it('rejects empty or inverted scopes', function () {
    expect(fn () => Scope::campuses([]))->toThrow(InvalidArgumentException::class)
        ->and(fn () => Scope::wing(['c1'], 5, 1))->toThrow(InvalidArgumentException::class);
});

it('round-trips through storage', function () {
    $scope = Scope::wing(['c2', 'c1'], 1, 5);

    $restored = Scope::fromStored($scope->type, $scope->toStored());

    expect($restored->toStored())->toBe($scope->toStored())
        ->and(Scope::school()->toStored())->toBeNull();
});

it('unions scopes for one permission', function () {
    $set = new ScopeSet([Scope::campuses(['c1']), Scope::sections(['s9'])]);

    expect($set->allows())->toBeTrue()
        ->and($set->allows(new ScopeTarget(campusUlid: 'c1')))->toBeTrue()
        ->and($set->allows(new ScopeTarget(sectionUlid: 's9')))->toBeTrue()
        ->and($set->allows(new ScopeTarget(campusUlid: 'c2')))->toBeFalse()
        ->and($set->isSchoolWide())->toBeFalse()
        ->and((new ScopeSet)->allows())->toBeFalse();
});
