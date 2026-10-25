<?php

use App\Domain\Access\Data\Scope;
use App\Domain\Audit\Models\AuditEvent;
use App\Domain\Fees\Contracts\FeeNotifier;
use App\Domain\Fees\Enums\PaymentStatus;
use App\Domain\Fees\Models\FeeInstallment;
use App\Domain\Fees\Models\FeeInvoice;
use App\Domain\Shared\CalendarDate;
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

    $this->riya = $this->makeStudent($this->main1A, 'Riya', 'Sharma');
    $this->kabir = $this->makeStudent($this->main1B, 'Kabir', 'Mehta');
    $this->anaya = $this->makeStudent($this->north1A, 'Anaya', 'Reddy');
    $this->arjun = $this->makeStudent($this->main2A, 'Arjun', 'Kapoor');
    $this->billAllTerms();
});

/**
 * The rows of a downloaded CSV, without its byte-order mark.
 *
 * @return list<list<string|null>>
 */
function csvRows(string $csv): array
{
    $lines = array_values(array_filter(explode("\n", ltrim($csv, "\xEF\xBB\xBF"))));

    return array_map(fn (string $line) => str_getcsv($line, ',', '"', ''), $lines);
}

/** Every installment of a student falls due on a fixed day, so the status does not depend on today. */
function dueAllOn($test, $student, string $date): void
{
    foreach ($test->invoicesOf($student) as $invoice) {
        $test->dueOn($invoice, $date);
    }
}

it('lists every unpaid installment of the year with ulids only and the totals', function () {
    $this->actingAs($this->owner)->get(route('fees.dues.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('fees/dues')
            ->where('screen.academic_year', '2026-27')
            ->where('screen.has_schedule', true)
            ->where('screen.summary.installments', 16)
            ->where('screen.summary.students', 4)
            ->where('screen.summary.balance_paise', 16 * 1050000)
            ->where('screen.summary.overdue_paise', (int) FeeInvoice::query()->where('due_on', '<', CalendarDate::today())->sum('net_paise'))
            ->has('screen.rows', 16)
            ->where('screen.total', 16)
            ->where('screen.from', 1)
            ->where('screen.to', 16)
            ->has('screen.rows.0', fn (Assert $row) => $row
                ->has('ulid')
                ->has('student_ulid')
                ->where('balance_paise', 1050000)
                ->where('paid_paise', 0)
                ->where('payment_pending', false)
                ->where('can_collect', true)
                ->where('can_remind', true)
                ->etc())
            ->has('screen.sections', 4)
            ->has('screen.installments', 4));
});

it('never sends internal ids to the page', function () {
    $json = $this->actingAs($this->owner)->get(route('fees.dues.index'))->getContent();

    expect($json)->not->toContain('student_id')->and($json)->not->toContain('"id":');
});

it('leaves out installments that are paid and students who have left', function () {
    $invoice = $this->invoicesOf($this->riya)[0];
    $invoice->forceFill(['paid_paise' => $invoice->net_paise])->save();
    $this->arjun->forceFill(['status' => 'left'])->save();

    $this->actingAs($this->owner)->get(route('fees.dues.index'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('screen.summary.installments', 16 - 1 - 4)
            ->where('screen.summary.students', 3));
});

it('filters by class, installment and search', function () {
    $this->actingAs($this->owner)->get(route('fees.dues.index', ['class' => $this->main1B->ulid]))
        ->assertInertia(fn (Assert $page) => $page->where('screen.summary.installments', 4)->where('screen.summary.students', 1));

    $installment = $this->invoicesOf($this->riya)[0]->installment_id;
    $ulid = FeeInstallment::query()->whereKey($installment)->value('ulid');

    $this->actingAs($this->owner)->get(route('fees.dues.index', ['installment' => $ulid]))
        ->assertInertia(fn (Assert $page) => $page->where('screen.summary.installments', 4)->where('screen.summary.students', 4));

    $this->actingAs($this->owner)->get(route('fees.dues.index', ['q' => 'kabir']))
        ->assertInertia(fn (Assert $page) => $page->where('screen.summary.students', 1)->where('screen.rows.0.student_name', 'Kabir Mehta'));

    $this->actingAs($this->owner)->get(route('fees.dues.index', ['q' => $this->arjun->admission_number]))
        ->assertInertia(fn (Assert $page) => $page->where('screen.summary.students', 1)->where('screen.rows.0.student_name', 'Arjun Kapoor'));

    $this->actingAs($this->owner)->get(route('fees.dues.index', ['q' => 'no such child']))
        ->assertInertia(fn (Assert $page) => $page->where('screen.total', 0)->has('screen.rows', 0));
});

it('filters by status using the due date and what has been paid', function () {
    dueAllOn($this, $this->riya, '2020-01-15');      // overdue
    dueAllOn($this, $this->kabir, '2099-01-15');     // upcoming
    $soon = (new DateTimeImmutable(CalendarDate::today()))->modify('+5 days')->format('Y-m-d');
    dueAllOn($this, $this->anaya, $soon);            // due soon
    dueAllOn($this, $this->arjun, '2099-02-15');
    $partial = $this->invoicesOf($this->arjun)[0];
    $partial->forceFill(['paid_paise' => 100000])->save();

    $count = fn (string $status) => $this->actingAs($this->owner)->get(route('fees.dues.index', ['status' => $status]))->viewData('page')['props']['screen']['summary']['installments'];

    expect($count('overdue'))->toBe(4)
        ->and($count('due'))->toBe(4)
        ->and($count('upcoming'))->toBe(4 + 3)
        ->and($count('partial'))->toBe(1)
        ->and($count('open'))->toBe(16);
});

it('sorts by due date, balance or class', function () {
    dueAllOn($this, $this->kabir, '2020-01-15');
    $big = $this->invoicesOf($this->riya)[0];
    $big->forceFill(['net_paise' => 2000000, 'gross_paise' => 2000000])->save();

    $first = fn (string $sort) => $this->actingAs($this->owner)->get(route('fees.dues.index', ['sort' => $sort]))->viewData('page')['props']['screen']['rows'][0];

    expect($first('due')['student_name'])->toBe('Kabir Mehta')
        ->and($first('balance')['student_name'])->toBe('Riya Sharma')
        ->and($first('class')['section_label'])->toBe('Class 1-A');
});

it('shows 20 rows a page', function () {
    foreach (['Dev', 'Esha', 'Fatima'] as $name) {
        $this->makeStudent($this->main1A, $name, 'Gill');
    }
    $this->billAllTerms();

    $this->actingAs($this->owner)->get(route('fees.dues.index'))
        ->assertInertia(fn (Assert $page) => $page->where('screen.total', 28)->has('screen.rows', 20)->where('screen.last_page', 2)->where('screen.to', 20));

    $this->actingAs($this->owner)->get(route('fees.dues.index', ['page' => 2]))
        ->assertInertia(fn (Assert $page) => $page->has('screen.rows', 8)->where('screen.from', 21)->where('screen.to', 28)->where('screen.current_page', 2));
});

it('follows the campus chosen in the top bar', function () {
    $this->actingAs($this->owner)->withSession(['school.campus' => $this->north->ulid])->get(route('fees.dues.index'))
        ->assertInertia(fn (Assert $page) => $page->where('screen.summary.students', 1)->where('screen.rows.0.campus_name', 'North Campus'));
});

it('shows only the sections the person\'s fees.view covers', function () {
    $campus = $this->staffWith(['fees.view'], Scope::campuses([$this->north->ulid]));
    $section = $this->staffWith(['fees.view'], Scope::sections([$this->main1B->ulid]));
    $wing = $this->staffWith(['fees.view'], Scope::wing([$this->main->ulid], 2, 2));

    $names = fn ($actor) => collect($this->actingAs($actor)->get(route('fees.dues.index'))->viewData('page')['props']['screen']['rows'])->pluck('student_name')->unique()->sort()->values()->all();

    expect($names($campus))->toBe(['Anaya Reddy'])
        ->and($names($section))->toBe(['Kabir Mehta'])
        ->and($names($wing))->toBe(['Arjun Kapoor']);
});

it('offers collect and remind only where the person holds those permissions over the student', function () {
    $viewer = $this->staffWith(['fees.view']);
    $collector = $this->staffWith(['fees.view', 'fees.collect'], Scope::campuses([$this->main->ulid]));

    $this->actingAs($viewer)->get(route('fees.dues.index'))
        ->assertInertia(fn (Assert $page) => $page->where('screen.rows.0.can_collect', false)->where('screen.rows.0.can_remind', false));

    $rows = collect($this->actingAs($collector)->get(route('fees.dues.index'))->viewData('page')['props']['screen']['rows']);
    expect($rows->where('can_collect', true))->not->toBeEmpty()
        ->and($rows->where('can_remind', true))->toBeEmpty();
});

it('marks an installment whose payment is still pending', function () {
    $invoice = $this->invoicesOf($this->riya)[0];
    $this->onlinePayment($this->riya, [$invoice], PaymentStatus::Pending);

    $rows = collect($this->actingAs($this->owner)->get(route('fees.dues.index', ['q' => 'riya']))->viewData('page')['props']['screen']['rows']);

    expect($rows->firstWhere('ulid', $invoice->ulid)['payment_pending'])->toBeTrue()
        ->and($rows->where('payment_pending', true))->toHaveCount(1);
});

it('sends reminders, stamps today and records an audit event', function () {
    $this->mock(FeeNotifier::class)->shouldReceive('remind')->once();

    $invoices = array_slice($this->invoicesOf($this->riya), 0, 2);
    $other = $this->invoicesOf($this->kabir)[0];

    $this->actingAs($this->owner)->post(route('fees.dues.remind'), [
        'invoices' => [$invoices[0]->ulid, $invoices[1]->ulid, $other->ulid],
    ])->assertSessionHasNoErrors();

    expect(FeeInvoice::query()->where('last_reminded_on', CalendarDate::today())->count())->toBe(3);

    $event = AuditEvent::query()->where('action', 'fees.reminders_sent')->firstOrFail();
    expect($event->after)->toBe(['invoices' => 3, 'students' => 2]);

    $this->actingAs($this->owner)->get(route('fees.dues.index', ['q' => 'kabir']))
        ->assertInertia(fn (Assert $page) => $page->where('screen.rows.0.reminded_today', true));
});

it('does not remind about an installment out of reach, a paid one, or without the permission', function () {
    $north = $this->invoicesOf($this->anaya)[0];
    $paid = $this->invoicesOf($this->riya)[0];
    $paid->forceFill(['paid_paise' => $paid->net_paise])->save();

    $mainOnly = $this->staffWith(['fees.view', 'fees.remind'], Scope::campuses([$this->main->ulid]));
    $this->actingAs($mainOnly)->post(route('fees.dues.remind'), ['invoices' => [$north->ulid]])->assertNotFound();
    $this->actingAs($this->owner)->post(route('fees.dues.remind'), ['invoices' => [$paid->ulid]])->assertSessionHasErrors('fees');
    $this->actingAs($this->owner)->post(route('fees.dues.remind'), ['invoices' => []])->assertSessionHasErrors('invoices');

    $viewer = $this->staffWith(['fees.view']);
    $this->actingAs($viewer)->post(route('fees.dues.remind'), ['invoices' => [$north->ulid]])->assertForbidden();

    expect(FeeInvoice::query()->whereNotNull('last_reminded_on')->count())->toBe(0);
});

it('exports the filtered dues as an audited CSV, with phones only for those who may see them', function () {
    $response = $this->actingAs($this->owner)->get(route('fees.dues.export', ['q' => 'kabir']));
    $csv = $response->streamedContent();

    $rows = csvRows($csv);

    expect($response->headers->get('content-type'))->toContain('text/csv')
        ->and($rows[0])->toBe(['Admission no', 'Student', 'Class', 'Campus', 'Installment', 'Due date', 'Net (₹)', 'Paid (₹)', 'Balance (₹)', 'Status', 'Primary guardian', 'Phone'])
        ->and($rows)->toHaveCount(5)
        ->and(array_values(array_unique(array_column(array_slice($rows, 1), 1))))->toBe(['Kabir Mehta'])
        ->and($csv)->not->toContain('Riya Sharma')
        ->and($rows[1][8])->toBe('10500')
        ->and($rows[1][10])->toBe('Kabir Parent Mehta')
        ->and($rows[1][11])->toMatch('/^91\d{10}$/');

    $event = AuditEvent::query()->where('action', 'fees.dues_exported')->firstOrFail();
    expect($event->after['rows'])->toBe(4)->and($event->after['has_search'])->toBeTrue();

    $viewer = $this->staffWith(['fees.view']);
    $masked = $this->actingAs($viewer)->get(route('fees.dues.export'))->streamedContent();
    expect($masked)->toContain('Kabir Mehta')
        ->and(array_values(array_unique(array_column(array_slice(csvRows($masked), 1), 11))))->toBe(['']);
});

it('applies the authorization matrix to the dues screen', function () {
    $this->assertAuthorizationMatrix(
        module: 'fees',
        permission: 'fees.view',
        inScope: Scope::school(),
        outOfScope: null,
        makeActor: fn (array $permissions, Scope $scope) => $this->staffWith($permissions, $scope),
        send: fn ($actor) => $this->actingAs($actor)->get(route('fees.dues.index')),
    );
});

it('applies the authorization matrix to sending reminders', function () {
    $this->mock(FeeNotifier::class)->shouldReceive('remind');
    $invoice = $this->invoicesOf($this->riya)[0];

    $this->assertAuthorizationMatrix(
        module: 'fees',
        permission: 'fees.remind',
        inScope: Scope::sections([$this->main1A->ulid]),
        outOfScope: Scope::sections([$this->north1A->ulid]),
        makeActor: fn (array $permissions, Scope $scope) => $this->staffWith($permissions, $scope),
        send: fn ($actor) => $this->actingAs($actor)->post(route('fees.dues.remind'), ['invoices' => [$invoice->ulid]]),
    );
});
