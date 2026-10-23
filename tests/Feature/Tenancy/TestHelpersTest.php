<?php

use App\Domain\Access\Data\Scope;
use App\Domain\Students\Models\Student;
use App\Tenancy\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\AssertsAuthorizationMatrix;
use Tests\Concerns\BuildsStudents;
use Tests\Concerns\HasCurrentSchool;
use Tests\Concerns\HasSecondSchool;
use Tests\Concerns\ManagesSchoolPlan;

uses(RefreshDatabase::class, HasCurrentSchool::class, HasSecondSchool::class, ManagesSchoolPlan::class, AssertsAuthorizationMatrix::class, BuildsStudents::class);

beforeEach(function () {
    // The owner role is built from the plan, so modules go in before the school is set up.
    $this->enableModules(['admissions']);
    $this->setUpStudentSchool();
});

it('keeps the second school in its own empty database', function () {
    $this->makeStudent($this->main1A);

    expect(Student::query()->count())->toBe(1)
        ->and($this->otherSchool->database_name)->not->toBe($this->school->database_name);

    $this->otherSchool->execute(function () {
        expect(Student::query()->count())->toBe(0);
    });

    expect(Tenant::current()?->is($this->school))->toBeTrue();
});

it('enables, disables and re-enables plan modules without duplicating rows', function () {
    $count = fn () => DB::connection('landlord')->table('tenant_modules')->where('tenant_id', $this->school->id)->count();

    $this->enableModules(['attendance', 'fees']);
    $this->enableModules(['attendance']);

    expect($count())->toBe(3)
        ->and($this->school->hasModule('attendance'))->toBeTrue();

    $this->disableModule('attendance');

    expect($this->school->hasModule('attendance'))->toBeFalse()
        ->and($this->school->hasModule('fees'))->toBeTrue();
});

it('runs the authorization matrix against a real route', function () {
    $this->assertAuthorizationMatrix(
        module: 'admissions',
        permission: 'admissions.view',
        inScope: Scope::school(),
        outOfScope: null,
        makeActor: fn (array $permissions, Scope $scope) => $this->staffWith($permissions, $scope),
        send: fn ($actor) => $this->actingAs($actor)->get(route('admissions.index')),
    );
});
