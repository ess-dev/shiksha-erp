<?php

use App\Domain\Access\Data\Scope;
use App\Domain\Audit\Models\AuditEvent;
use App\Domain\Fees\Actions\SaveFeeGroupHeads;
use App\Domain\Fees\Models\FeeConcession;
use App\Domain\Fees\Models\FeeGroup;
use App\Domain\Fees\Models\FeeGroupClass;
use App\Domain\Fees\Models\FeeInstallment;
use App\Domain\Fees\Models\FeeInvoice;
use App\Domain\School\Actions\CreateSchoolClass;
use App\Domain\School\Actions\CreateSection;
use App\Domain\School\Models\AcademicYear;
use App\Domain\School\Models\SchoolClass;
use App\Domain\Students\Data\NewGuardian;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Concerns\AssertsAuthorizationMatrix;
use Tests\Concerns\BuildsFees;
use Tests\Concerns\BuildsStudents;
use Tests\Concerns\HasCurrentSchool;
use Tests\Concerns\ManagesSchoolPlan;

uses(RefreshDatabase::class, HasCurrentSchool::class, ManagesSchoolPlan::class, BuildsStudents::class, BuildsFees::class, AssertsAuthorizationMatrix::class);

beforeEach(function () {
    $this->enableModules(['fees']);
    $this->setUpStudentSchool();
    $this->setUpFeeStructure();
});

it('creates a four-term schedule from the academic year, once, with the sibling concession', function () {
    $installments = FeeInstallment::query()->orderBy('position')->get();

    expect($installments)->toHaveCount(4)
        ->and($installments->pluck('label')->all())->toBe(['Term 1 (Apr–Jun)', 'Term 2 (Jul–Sep)', 'Term 3 (Oct–Dec)', 'Term 4 (Jan–Mar)'])
        ->and($installments->pluck('due_on')->all())->toBe(['2026-04-15', '2026-07-15', '2026-10-15', '2027-01-15'])
        ->and($installments->pluck('ends_on')->all())->toBe(['2026-06-30', '2026-09-30', '2026-12-31', '2027-03-31'])
        ->and(FeeConcession::query()->where('kind', 'sibling')->count())->toBe(1)
        ->and(AuditEvent::query()->where('action', 'fees.schedule_created')->count())->toBe(1);

    $this->actingAs($this->owner)->post(route('fees.structure.schedule'))
        ->assertSessionHasErrors('fees');

    expect(FeeInstallment::query()->count())->toBe(4);
});

it('bills a newly enrolled student only for terms that have not ended and prices them from the class fee group', function () {
    $student = $this->makeStudent($this->main1A);
    $invoices = $this->invoicesOf($student);
    $expected = FeeInstallment::query()->where('ends_on', '>=', $student->admitted_on)->count();

    expect($invoices)->toHaveCount($expected)
        ->and($invoices[0]->gross_paise)->toBe(1050000)
        ->and($invoices[0]->net_paise)->toBe(1050000)
        ->and($invoices[0]->paid_paise)->toBe(0)
        ->and($invoices[0]->heads)->toBe([
            ['name' => 'Tuition fee', 'amount_paise' => 840000],
            ['name' => 'Development fee', 'amount_paise' => 120000],
            ['name' => 'Activity fee', 'amount_paise' => 90000],
        ])
        ->and(AuditEvent::query()->where('action', 'fees.student_billed')->count())->toBe(1);
});

it('bills every term on request and never twice', function () {
    $student = $this->makeStudent($this->main1A);
    $this->billAllTerms();
    $this->billAllTerms();

    expect($this->invoicesOf($student))->toHaveCount(4);
});

it('gives the second child onward the sibling concession on tuition, and not the first', function () {
    $phone = $this->nextPhone();
    $first = $this->makeStudent($this->main1A, 'Riya', 'Sharma', [new NewGuardian('Priya Sharma', 'Mother', $phone, null, null, true)]);
    $second = $this->makeStudent($this->main2A, 'Arjun', 'Sharma', [new NewGuardian('Priya Sharma', 'Mother', $phone, null, null, true)]);
    $this->billAllTerms();

    $elder = $this->invoicesOf($first)[0];
    $younger = $this->invoicesOf($second)[0];

    expect($elder->concession_paise)->toBe(0)
        ->and($elder->net_paise)->toBe(1050000)
        ->and($younger->concession_paise)->toBe(84000)
        ->and($younger->gross_paise)->toBe(1050000)
        ->and($younger->net_paise)->toBe(966000)
        ->and($younger->concession_note)->toBe('Sibling concession (10% of tuition fee)');
});

it('re-prices only installments due later with nothing paid when the heads change', function () {
    $student = $this->makeStudent($this->main1A);
    $this->billAllTerms();
    [$first, $second, $third, $fourth] = $this->invoicesOf($student);

    $this->dueOn($first, '2020-01-15');                 // already billed and past due
    $this->dueOn($second, '2099-01-15');                // future, nothing paid
    $this->dueOn($third, '2099-02-15');                 // future, part paid
    $third->forceFill(['paid_paise' => 100000])->save();
    $this->dueOn($fourth, '2099-03-15');                // future, nothing paid

    $result = app(SaveFeeGroupHeads::class)->handle($this->owner, $this->feeGroup, [
        ['name' => 'Tuition fee', 'amount' => 10000],
        ['name' => 'Development fee', 'amount' => 1500],
    ]);

    expect($result)->toBe(['repriced' => 2, 'kept' => 1])
        ->and($first->fresh()->gross_paise)->toBe(1050000)
        ->and($second->fresh()->gross_paise)->toBe(1150000)
        ->and($second->fresh()->heads)->toBe([
            ['name' => 'Tuition fee', 'amount_paise' => 1000000],
            ['name' => 'Development fee', 'amount_paise' => 150000],
        ])
        ->and($third->fresh()->gross_paise)->toBe(1050000)
        ->and($fourth->fresh()->net_paise)->toBe(1150000);

    $event = AuditEvent::query()->where('action', 'fees.group_heads_saved')->latest('id')->firstOrFail();
    expect($event->after['repriced'])->toBe(2)->and($event->after['kept'])->toBe(1);
});

it('re-prices a sibling concession with the new tuition', function () {
    $phone = $this->nextPhone();
    $this->makeStudent($this->main1A, 'Riya', 'Sharma', [new NewGuardian('Priya Sharma', 'Mother', $phone, null, null, true)]);
    $younger = $this->makeStudent($this->main2A, 'Arjun', 'Sharma', [new NewGuardian('Priya Sharma', 'Mother', $phone, null, null, true)]);
    $this->billAllTerms();

    foreach ($this->invoicesOf($younger) as $invoice) {
        $this->dueOn($invoice, '2099-01-15');
    }

    app(SaveFeeGroupHeads::class)->handle($this->owner, $this->feeGroup, [['name' => 'Tuition fee', 'amount' => 10000]]);

    $invoice = $this->invoicesOf($younger)[0];
    expect($invoice->gross_paise)->toBe(1000000)
        ->and($invoice->concession_paise)->toBe(100000)
        ->and($invoice->net_paise)->toBe(900000);
});

it('rejects fee heads with no name, a repeated name or a bad amount', function (array $heads) {
    $this->actingAs($this->owner)
        ->put(route('fees.structure.groups.update', $this->feeGroup), ['heads' => $heads])
        ->assertSessionHasErrors();

    expect($this->feeGroup->fresh()->termPaise())->toBe(1050000);
})->with([
    'no name' => [[['name' => '', 'amount' => 100]]],
    'negative amount' => [[['name' => 'Tuition fee', 'amount' => -5]]],
    'not a number' => [[['name' => 'Tuition fee', 'amount' => 'abc']]],
    'no heads' => [[]],
]);

it('rejects two fee heads with the same name', function () {
    $this->actingAs($this->owner)
        ->put(route('fees.structure.groups.update', $this->feeGroup), ['heads' => [
            ['name' => 'Tuition fee', 'amount' => 100],
            ['name' => 'tuition FEE', 'amount' => 200],
        ]])
        ->assertSessionHasErrors('fees');
});

it('moves a class to another group, re-pricing its future unpaid installments and billing students never billed', function () {
    $student = $this->makeStudent($this->main2A, 'Arjun', 'Sharma');
    $this->billAllTerms();

    foreach ($this->invoicesOf($student) as $invoice) {
        $this->dueOn($invoice, '2099-01-15');
    }

    $class3 = app(CreateSchoolClass::class)->handle($this->owner, 'Class 3', 3);
    $section = app(CreateSection::class)->handle(
        $this->owner,
        AcademicYear::query()->firstOrFail(),
        $this->main,
        $class3,
        'A',
        40,
    );
    $unbilled = $this->makeStudent($section, 'Dev', 'Kapoor');

    expect($this->invoicesOf($unbilled))->toBe([]);

    $senior = $this->actingAs($this->owner)->post(route('fees.structure.groups.store'), [
        'name' => 'Classes 3–5',
        'copy_from' => $this->feeGroup->ulid,
        'classes' => [$class3->ulid],
    ]);
    $senior->assertSessionHasNoErrors();

    $group = FeeGroup::query()->where('name', 'Classes 3–5')->firstOrFail();
    expect(FeeGroupClass::query()->where('fee_group_id', $group->id)->count())->toBe(1)
        ->and(count($this->invoicesOf($unbilled)))->toBeGreaterThan(0);

    $this->actingAs($this->owner)
        ->put(route('fees.structure.groups.update', $group), ['heads' => [['name' => 'Tuition fee', 'amount' => 20000]]])
        ->assertSessionHasNoErrors();

    $this->actingAs($this->owner)
        ->put(route('fees.structure.classes.assign', SchoolClass::query()->where('level', 2)->value('ulid')), ['group' => $group->ulid])
        ->assertSessionHasNoErrors();

    $moved = $this->invoicesOf($student);
    expect($moved[0]->fee_group_id)->toBe($group->id)
        ->and($moved[0]->gross_paise)->toBe(2000000)
        ->and(AuditEvent::query()->where('action', 'fees.class_moved')->count())->toBeGreaterThan(0);
});

it('gives a class its own fees by copying its group, and refuses a class that already has its own', function () {
    $class1 = SchoolClass::query()->where('level', 1)->value('ulid');

    $this->actingAs($this->owner)->post(route('fees.structure.groups.store'), [
        'name' => 'Class 1',
        'copy_from' => $this->feeGroup->ulid,
        'classes' => [$class1],
    ])->assertSessionHasNoErrors();

    $own = FeeGroup::query()->where('name', 'Class 1')->firstOrFail();
    expect($own->termPaise())->toBe(1050000)
        ->and(FeeGroupClass::query()->where('fee_group_id', $own->id)->count())->toBe(1)
        ->and(FeeGroupClass::query()->where('fee_group_id', $this->feeGroup->id)->count())->toBe(1);

    $this->actingAs($this->owner)->post(route('fees.structure.groups.store'), [
        'name' => 'Class 1 again',
        'classes' => [$class1],
    ])->assertSessionHasErrors('fees');

    expect(FeeGroup::query()->count())->toBe(2);
});

it('bills nobody while a group has no fee heads, and bills its students once the heads are saved', function () {
    $class3 = app(CreateSchoolClass::class)->handle($this->owner, 'Class 3', 3);
    $section = app(CreateSection::class)->handle($this->owner, AcademicYear::query()->firstOrFail(), $this->main, $class3, 'A', 40);
    $student = $this->makeStudent($section, 'Dev', 'Kapoor');

    $this->actingAs($this->owner)->post(route('fees.structure.groups.store'), ['name' => 'Class 3', 'classes' => [$class3->ulid]])
        ->assertSessionHasNoErrors();

    expect($this->invoicesOf($student))->toBe([]);

    $group = FeeGroup::query()->where('name', 'Class 3')->firstOrFail();
    $this->actingAs($this->owner)
        ->put(route('fees.structure.groups.update', $group), ['heads' => [['name' => 'Tuition fee', 'amount' => 5000]]])
        ->assertSessionHasNoErrors();

    $invoices = $this->invoicesOf($student);
    expect(count($invoices))->toBeGreaterThan(0)
        ->and($invoices[0]->gross_paise)->toBe(500000)
        ->and(AuditEvent::query()->where('action', 'fees.group_heads_saved')->latest('id')->firstOrFail()->after['billed'])->toBe(count($invoices));
});

it('renames a group and refuses a name another group has', function () {
    $other = $this->actingAs($this->owner)->post(route('fees.structure.groups.store'), ['name' => 'Classes 6–8'])
        ->assertSessionHasNoErrors();
    $group = FeeGroup::query()->where('name', 'Classes 6–8')->firstOrFail();

    $this->actingAs($this->owner)->patch(route('fees.structure.groups.rename', $group), ['name' => 'Middle school'])->assertSessionHasNoErrors();
    expect($group->fresh()->name)->toBe('Middle school');

    $this->actingAs($this->owner)->patch(route('fees.structure.groups.rename', $group), ['name' => 'classes 1–2'])->assertSessionHasErrors('fees');
    expect($group->fresh()->name)->toBe('Middle school');
});

it('deletes only a group with no classes that was never billed', function () {
    $this->actingAs($this->owner)->post(route('fees.structure.groups.store'), ['name' => 'Spare group'])->assertSessionHasNoErrors();
    $spare = FeeGroup::query()->where('name', 'Spare group')->firstOrFail();

    $this->actingAs($this->owner)->delete(route('fees.structure.groups.destroy', $this->feeGroup))->assertSessionHasErrors('fees');
    $this->actingAs($this->owner)->delete(route('fees.structure.groups.destroy', $spare))->assertSessionHasNoErrors();

    expect(FeeGroup::query()->whereKey($spare->id)->exists())->toBeFalse()
        ->and(FeeGroup::query()->whereKey($this->feeGroup->id)->exists())->toBeTrue();
});

it('adds a concession on an existing fee head and flags a percentage above 50 for approval', function () {
    $this->actingAs($this->owner)->post(route('fees.structure.concessions.store'), [
        'name' => 'Staff ward', 'type' => 'percent', 'value' => 60, 'head' => 'tuition fee',
    ])->assertSessionHasNoErrors();

    $this->actingAs($this->owner)->post(route('fees.structure.concessions.store'), [
        'name' => 'Merit scholarship', 'type' => 'flat', 'value' => 2000, 'head' => 'Tuition fee',
    ])->assertSessionHasNoErrors();

    $staff = FeeConcession::query()->where('name', 'Staff ward')->firstOrFail();
    $merit = FeeConcession::query()->where('name', 'Merit scholarship')->firstOrFail();

    expect($staff->value)->toBe(60)->and($staff->needs_approval)->toBeTrue()->and($staff->applies_to_head)->toBe('Tuition fee')
        ->and($merit->value)->toBe(200000)->and($merit->needs_approval)->toBeFalse();
});

it('refuses a concession with a bad value, an unknown fee head or a repeated name', function (array $input) {
    $this->actingAs($this->owner)->post(route('fees.structure.concessions.store'), array_replace([
        'name' => 'Sports scholarship', 'type' => 'percent', 'value' => 25, 'head' => 'Tuition fee',
    ], $input))->assertSessionHasErrors();

    expect(FeeConcession::query()->where('kind', 'standard')->count())->toBe(0);
})->with([
    'over a hundred percent' => [['value' => 150]],
    'zero' => [['value' => 0]],
    'unknown fee head' => [['head' => 'Transport fee']],
    'no name' => [['name' => '']],
    'repeats the sibling concession' => [['name' => 'sibling concession']],
]);

it('shows the structure with ulids only, per-term totals and student counts', function () {
    $this->makeStudent($this->main1A);
    $this->makeStudent($this->main1B, 'Kabir', 'Mehta');

    $this->actingAs($this->owner)->get(route('fees.structure.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('fees/structure')
            ->where('structure.academic_year', '2026-27')
            ->where('structure.has_schedule', true)
            ->where('structure.can_manage', true)
            ->where('structure.classes.0.name', 'Class 1')
            ->where('structure.classes.0.students', 2)
            ->where('structure.classes.0.term_paise', 1050000)
            ->where('structure.groups.0.name', 'Classes 1–2')
            ->where('structure.groups.0.term_paise', 1050000)
            ->where('structure.groups.0.year_paise', 4200000)
            ->where('structure.groups.0.class_names', ['Class 1', 'Class 2'])
            ->where('structure.groups.0.can_delete', false)
            ->where('structure.installments.0.label', 'Term 1 (Apr–Jun)')
            ->where('structure.concessions.0.name', 'Sibling concession')
            ->where('structure.concessions.0.description', '10% of tuition fee · applied automatically to the second child onward')
            ->where('structure.head_names', ['Tuition fee', 'Development fee', 'Activity fee'])
            ->where('structure.classes_without_group', [])
            ->etc());
});

it('lists a class that has no fee group so the warning can show', function () {
    app(CreateSchoolClass::class)->handle($this->owner, 'Class 11', 11);

    $this->actingAs($this->owner)->get(route('fees.structure.index'))
        ->assertInertia(fn (Assert $page) => $page->where('structure.classes_without_group', ['Class 11'])->etc());
});

it('lets someone with only fees.view read the structure but not change it', function () {
    $viewer = $this->staffWith(['fees.view']);

    $this->actingAs($viewer)->get(route('fees.structure.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->where('structure.can_manage', false)->etc());

    $this->actingAs($viewer)->put(route('fees.structure.groups.update', $this->feeGroup), ['heads' => [['name' => 'Tuition fee', 'amount' => 1]]])->assertForbidden();
    $this->actingAs($viewer)->post(route('fees.structure.schedule'))->assertForbidden();
});

it('lets a campus-scoped manager read but not change the school-wide structure', function () {
    $manager = $this->staffWith(['fees.view', 'fee_structures.manage'], Scope::campuses([$this->main->ulid]));

    $this->actingAs($manager)->put(route('fees.structure.groups.update', $this->feeGroup), ['heads' => [['name' => 'Tuition fee', 'amount' => 1]]])->assertForbidden();
    expect($this->feeGroup->fresh()->termPaise())->toBe(1050000);
});

it('applies the authorization matrix to the structure screen', function () {
    $this->assertAuthorizationMatrix(
        module: 'fees',
        permission: 'fees.view',
        inScope: Scope::school(),
        outOfScope: null,
        makeActor: fn (array $permissions, Scope $scope) => $this->staffWith($permissions, $scope),
        send: fn ($actor) => $this->actingAs($actor)->get(route('fees.structure.index')),
    );
});

it('writes an audit event for every structure change', function () {
    $this->actingAs($this->owner)->post(route('fees.structure.groups.store'), ['name' => 'Classes 6–8'])->assertSessionHasNoErrors();
    $group = FeeGroup::query()->where('name', 'Classes 6–8')->firstOrFail();
    $this->actingAs($this->owner)->patch(route('fees.structure.groups.rename', $group), ['name' => 'Middle'])->assertSessionHasNoErrors();
    $this->actingAs($this->owner)->post(route('fees.structure.concessions.store'), ['name' => 'Merit', 'type' => 'percent', 'value' => 5, 'head' => 'Tuition fee'])->assertSessionHasNoErrors();
    $this->actingAs($this->owner)->delete(route('fees.structure.groups.destroy', $group))->assertSessionHasNoErrors();

    foreach (['fees.group_created', 'fees.group_renamed', 'fees.concession_added', 'fees.group_deleted'] as $action) {
        expect(AuditEvent::query()->where('action', $action)->exists())->toBeTrue($action);
    }
});

it('keeps billed invoices when their group is renamed', function () {
    $student = $this->makeStudent($this->main1A);
    $before = $this->invoicesOf($student)[0]->only(['gross_paise', 'heads']);

    $this->actingAs($this->owner)->patch(route('fees.structure.groups.rename', $this->feeGroup), ['name' => 'Primary'])->assertSessionHasNoErrors();

    expect($this->invoicesOf($student)[0]->only(['gross_paise', 'heads']))->toBe($before)
        ->and(FeeInvoice::query()->count())->toBeGreaterThan(0);
});
