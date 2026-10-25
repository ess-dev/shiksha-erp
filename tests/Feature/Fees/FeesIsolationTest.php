<?php

use App\Domain\Fees\Models\FeeConcession;
use App\Domain\Fees\Models\FeeGroup;
use App\Domain\Fees\Models\FeeInstallment;
use App\Domain\Fees\Models\FeeInvoice;
use App\Domain\Fees\Models\FeePayment;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Concerns\BuildsFees;
use Tests\Concerns\BuildsStudents;
use Tests\Concerns\HasCurrentSchool;
use Tests\Concerns\HasSecondSchool;
use Tests\Concerns\ManagesSchoolPlan;

uses(RefreshDatabase::class, HasCurrentSchool::class, HasSecondSchool::class, ManagesSchoolPlan::class, BuildsStudents::class, BuildsFees::class);

it('keeps one school\'s fees out of another school', function () {
    $this->enableModules(['fees']);
    $this->setUpStudentSchool();
    $this->setUpFeeStructure();

    $student = $this->makeStudent($this->main1A, 'Riya', 'Sharma');
    $this->billAllTerms();
    $this->actingAs($this->owner)->post(route('fees.collect.store'), [
        'student' => $student->ulid,
        'invoices' => [$this->invoicesOf($student)[0]->ulid],
        'mode' => 'cash',
        'idempotency_key' => (string) Str::uuid(),
    ])->assertSessionHasNoErrors();

    expect(FeeInvoice::query()->count())->toBe(4)
        ->and(FeePayment::query()->count())->toBe(1);

    $other = $this->otherSchool->execute(fn () => [
        'installments' => FeeInstallment::query()->count(),
        'groups' => FeeGroup::query()->count(),
        'concessions' => FeeConcession::query()->count(),
        'invoices' => FeeInvoice::query()->count(),
        'payments' => FeePayment::query()->count(),
    ]);

    expect($other)->toBe(['installments' => 0, 'groups' => 0, 'concessions' => 0, 'invoices' => 0, 'payments' => 0]);
});

it('numbers receipts per school', function () {
    $this->enableModules(['fees']);
    $this->setUpStudentSchool();
    $this->setUpFeeStructure();

    $student = $this->makeStudent($this->main1A, 'Riya', 'Sharma');
    $this->billAllTerms();
    $this->actingAs($this->owner)->post(route('fees.collect.store'), [
        'student' => $student->ulid, 'invoices' => [$this->invoicesOf($student)[0]->ulid], 'mode' => 'cash', 'idempotency_key' => (string) Str::uuid(),
    ]);

    $sequences = $this->otherSchool->execute(fn () => DB::connection('tenant')->table('number_sequences')->where('name', 'like', 'receipt:%')->count());

    expect($sequences)->toBe(0)
        ->and(DB::connection('tenant')->table('number_sequences')->where('name', 'like', 'receipt:%')->count())->toBe(1);
});

it('shows a school with no installment schedule an empty, explained screen', function () {
    $this->enableModules(['fees']);
    $this->setUpStudentSchool();
    $this->makeStudent($this->main1A, 'Riya', 'Sharma');

    $this->actingAs($this->owner)->get(route('fees.dues.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->where('screen.has_schedule', false)->where('screen.total', 0)->has('screen.rows', 0));

    $this->actingAs($this->owner)->get(route('fees.collect.index'))
        ->assertInertia(fn (Assert $page) => $page->where('screen.has_schedule', false));

    $this->actingAs($this->owner)->get(route('fees.structure.index'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('structure.has_schedule', false)
            ->where('structure.installments', [])
            ->where('structure.classes_without_group', ['Class 1', 'Class 2']));

    expect(FeeInvoice::query()->count())->toBe(0);
});

it('bills students who were enrolled before the schedule and groups existed', function () {
    $this->enableModules(['fees']);
    $this->setUpStudentSchool();
    $student = $this->makeStudent($this->main1A, 'Riya', 'Sharma');

    $this->setUpFeeStructure();

    expect(FeeInvoice::query()->where('student_id', $student->id)->count())->toBeGreaterThan(0);
});
