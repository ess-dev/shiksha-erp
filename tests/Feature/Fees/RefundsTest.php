<?php

use App\Domain\Access\Actions\AssignRole;
use App\Domain\Access\Actions\CreateRole;
use App\Domain\Access\Actions\CreateStaffRecord;
use App\Domain\Access\Data\Scope;
use App\Domain\Audit\Models\AuditEvent;
use App\Domain\Fees\Contracts\FeeNotifier;
use App\Domain\Fees\Enums\RefundMethod;
use App\Domain\Fees\Enums\RefundSource;
use App\Domain\Fees\Enums\RefundStatus;
use App\Domain\Fees\Models\FeeInvoice;
use App\Domain\Fees\Models\FeePayment;
use App\Domain\Fees\Models\FeeRefund;
use App\Domain\Fees\PaymentSettlement;
use App\Domain\Identity\Models\Identity;
use App\Domain\School\Models\SchoolSetting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Concerns\AssertsAuthorizationMatrix;
use Tests\Concerns\BuildsFees;
use Tests\Concerns\BuildsStudents;
use Tests\Concerns\HasCurrentSchool;
use Tests\Concerns\HasSecondSchool;
use Tests\Concerns\ManagesSchoolPlan;

uses(RefreshDatabase::class, HasCurrentSchool::class, HasSecondSchool::class, ManagesSchoolPlan::class, BuildsStudents::class, BuildsFees::class, AssertsAuthorizationMatrix::class);

beforeEach(function () {
    $this->enableModules(['fees']);
    $this->setUpStudentSchool();
    $this->setUpFeeStructure();

    $this->riya = $this->makeStudent($this->main1A, 'Riya', 'Sharma');
    $this->anaya = $this->makeStudent($this->north1A, 'Anaya', 'Reddy');
    $this->billAllTerms();
    $this->invoices = $this->invoicesOf($this->riya);
});

/**
 * A staff identity holding permissions over a scope. `fees.refund` is critical, so the
 * grant is confirmed and the person has two-step set up (the staff area needs it).
 *
 * @param  list<string>  $permissions
 */
function refundStaff(array $permissions, Scope $scope): Identity
{
    $identity = test()->staff();
    $staff = app(CreateStaffRecord::class)->handle($identity->ulid, 'Staff '.uniqid());
    $role = app(CreateRole::class)->handle(test()->owner, 'Role '.uniqid(), $permissions, null, true);
    app(AssignRole::class)->handle(test()->owner, $staff, $role, $scope, true);
    $identity->forceFill(['two_factor_secret' => encrypt('secret'), 'two_factor_confirmed_at' => now()])->save();

    return $identity;
}

/** A confirmed cash payment of one installment (₹10,500 in the fictional fee group). */
function paidInCash(FeeInvoice $invoice, $student): FeePayment
{
    test()->actingAs(test()->owner)->post(route('fees.collect.store'), [
        'student' => $student->ulid, 'invoices' => [$invoice->ulid], 'mode' => 'cash', 'idempotency_key' => (string) Str::uuid(),
    ])->assertSessionHasNoErrors();

    return FeePayment::query()->latest('id')->firstOrFail();
}

/** A refund request raised through the real route, for an amount in rupees. */
function requestedRefund($student, int $rupees, ?FeePayment $payment = null): FeeRefund
{
    test()->actingAs(test()->owner)->post(route('fees.refunds.store'), [
        'student' => $student->ulid, 'payment' => $payment?->ulid, 'amount' => $rupees, 'reason' => 'Withdrawal before term start',
    ])->assertSessionHasNoErrors();

    return FeeRefund::query()->latest('id')->firstOrFail();
}

it('raises a pending refund when a confirmed payment could not be applied', function () {
    paidInCash($this->invoices[0], $this->riya);
    $late = $this->onlinePayment($this->riya, [$this->invoices[0]]);
    $late->forceFill(['amount_paise' => 1050000])->save();

    app(PaymentSettlement::class)->confirm($late, '2026-04-01');
    app(PaymentSettlement::class)->confirm($late->refresh(), '2026-04-01');

    $refund = FeeRefund::query()->sole();

    expect($refund->source)->toBe(RefundSource::System)
        ->and($refund->status)->toBe(RefundStatus::Pending)
        ->and($refund->amount_paise)->toBe(1050000)
        ->and($refund->fee_payment_id)->toBe($late->id)
        ->and($refund->student_id)->toBe($this->riya->id);
});

it('lists refunds by tab with totals and the sign-off limit', function () {
    $payment = paidInCash($this->invoices[0], $this->riya);
    $first = requestedRefund($this->riya, 3500, $payment);
    $second = requestedRefund($this->riya, 1200, $payment);
    $second->forceFill(['status' => RefundStatus::Approved, 'method' => RefundMethod::BankTransfer])->save();

    $this->actingAs($this->owner)->get(route('fees.refunds.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('fees/refunds')
            ->where('screen.tab', 'pending')
            ->where('screen.pending_count', 1)
            ->where('screen.pending_paise', 350000)
            ->where('screen.approved_count', 1)
            ->where('screen.approved_paise', 120000)
            ->where('screen.limit_paise', 1000000)
            ->has('screen.rows', 1)
            ->where('screen.rows.0.ulid', $first->ulid)
            ->where('screen.rows.0.student_name', 'Riya Sharma')
            ->where('screen.rows.0.original_label', 'Original Cash')
            ->where('screen.rows.0.source', 'accounts')
            ->missing('screen.rows.0.id'));

    $this->actingAs($this->owner)->get(route('fees.refunds.index', ['tab' => 'all']))
        ->assertInertia(fn (Assert $page) => $page->has('screen.rows', 2));
});

it('finds students with a confirmed payment for a new request', function () {
    paidInCash($this->invoices[0], $this->riya);

    $this->actingAs($this->owner)->get(route('fees.refunds.index', ['q' => 'riy']))
        ->assertInertia(fn (Assert $page) => $page
            ->has('screen.candidates', 1)
            ->where('screen.candidates.0.name', 'Riya Sharma')
            ->where('screen.candidates.0.refundable_paise', 1050000)
            ->has('screen.candidates.0.payments', 1));

    $this->actingAs($this->owner)->get(route('fees.refunds.index', ['q' => 'anaya']))
        ->assertInertia(fn (Assert $page) => $page->has('screen.candidates', 0));
});

it('adds a refund request, audited, and never above what was received', function () {
    $payment = paidInCash($this->invoices[0], $this->riya);

    $refund = requestedRefund($this->riya, 9800, $payment);

    expect($refund->status)->toBe(RefundStatus::Pending)
        ->and($refund->source)->toBe(RefundSource::Accounts)
        ->and($refund->amount_paise)->toBe(980000)
        ->and(AuditEvent::query()->where('action', 'fees.refund_requested')->count())->toBe(1);

    $this->actingAs($this->owner)->post(route('fees.refunds.store'), [
        'student' => $this->riya->ulid, 'payment' => $payment->ulid, 'amount' => 701, 'reason' => 'Fee charged in error',
    ])->assertSessionHasErrors('fees');

    $this->actingAs($this->owner)->post(route('fees.refunds.store'), [
        'student' => $this->riya->ulid, 'amount' => 0, 'reason' => 'x',
    ])->assertSessionHasErrors(['amount', 'reason']);

    expect(FeeRefund::query()->count())->toBe(1);
});

it('refuses a request for a student with nothing received', function () {
    $this->actingAs($this->owner)->post(route('fees.refunds.store'), [
        'student' => $this->riya->ulid, 'amount' => 100, 'reason' => 'Fee charged in error',
    ])->assertSessionHasErrors('fees');
});

it('approves a refund, records how it was paid and tells the parent', function () {
    $this->mock(FeeNotifier::class)->shouldReceive('refundDecided')->once()->with(Mockery::type('string'), true);

    $refund = requestedRefund($this->riya, 3500, paidInCash($this->invoices[0], $this->riya));
    $paidBefore = $this->invoices[0]->refresh()->paid_paise;

    $this->actingAs($this->owner)->post(route('fees.refunds.approve', $refund), [
        'method' => 'bank_transfer', 'reference' => 'UTR123456',
    ])->assertSessionHasNoErrors();

    $refund->refresh();

    expect($refund->status)->toBe(RefundStatus::Approved)
        ->and($refund->method)->toBe(RefundMethod::BankTransfer)
        ->and($refund->reference)->toBe('UTR123456')
        ->and($refund->decided_at)->not->toBeNull()
        ->and($refund->principal_signed_off)->toBeFalse()
        ->and($this->invoices[0]->refresh()->paid_paise)->toBe($paidBefore)
        ->and(AuditEvent::query()->where('action', 'fees.refund_approved')->count())->toBe(1);

    $this->actingAs($this->owner)->post(route('fees.refunds.approve', $refund), ['method' => 'cheque'])
        ->assertSessionHasErrors('fees');
});

it('needs the principal sign-off above the school limit', function () {
    $refund = requestedRefund($this->riya, 10500, paidInCash($this->invoices[0], $this->riya));

    $this->actingAs($this->owner)->post(route('fees.refunds.approve', $refund), ['method' => 'original'])
        ->assertSessionHasErrors('fees');
    expect($refund->refresh()->status)->toBe(RefundStatus::Pending);

    $this->actingAs($this->owner)->post(route('fees.refunds.approve', $refund), ['method' => 'original', 'principal_signed_off' => true])
        ->assertSessionHasNoErrors();
    expect($refund->refresh()->status)->toBe(RefundStatus::Approved)
        ->and($refund->principal_signed_off)->toBeTrue();
});

it('uses the limit the school saved', function () {
    SchoolSetting::query()->updateOrCreate(['key' => 'fees.refund_principal_limit_paise'], ['value' => 100000]);

    $refund = requestedRefund($this->riya, 1500, paidInCash($this->invoices[0], $this->riya));

    $this->actingAs($this->owner)->get(route('fees.refunds.index'))
        ->assertInertia(fn (Assert $page) => $page->where('screen.limit_paise', 100000)->where('screen.rows.0.needs_principal', true));

    $this->actingAs($this->owner)->post(route('fees.refunds.approve', $refund), ['method' => 'original'])
        ->assertSessionHasErrors('fees');
});

it('credits an adjustment to the next unpaid installments, oldest first', function () {
    $refund = requestedRefund($this->riya, 3000, paidInCash($this->invoices[0], $this->riya));

    $this->actingAs($this->owner)->post(route('fees.refunds.approve', $refund), ['method' => 'adjustment'])
        ->assertSessionHasNoErrors();

    $refund->refresh();

    expect($this->invoices[1]->refresh()->paid_paise)->toBe(300000)
        ->and($this->invoices[2]->refresh()->paid_paise)->toBe(0)
        ->and($refund->applied_to)->toBe([['invoice' => $this->invoices[1]->ulid, 'amount_paise' => 300000]]);
});

it('refuses an adjustment bigger than what is unpaid and changes nothing', function () {
    foreach ($this->invoices as $invoice) {
        $payment = paidInCash($invoice, $this->riya);
    }

    $refund = requestedRefund($this->riya, 3000, $payment);

    $this->actingAs($this->owner)->post(route('fees.refunds.approve', $refund), ['method' => 'adjustment'])
        ->assertSessionHasErrors('fees');

    expect($refund->refresh()->status)->toBe(RefundStatus::Pending)
        ->and(FeeInvoice::query()->where('student_id', $this->riya->id)->sum('paid_paise'))->toEqual(4 * 1050000);
});

it('rejects a refund with a reason, once', function () {
    $this->mock(FeeNotifier::class)->shouldReceive('refundDecided')->once()->with(Mockery::type('string'), false);

    $refund = requestedRefund($this->riya, 3500, paidInCash($this->invoices[0], $this->riya));

    $this->actingAs($this->owner)->post(route('fees.refunds.reject', $refund), ['reason' => 'x'])
        ->assertSessionHasErrors('reason');

    $this->actingAs($this->owner)->post(route('fees.refunds.reject', $refund), ['reason' => 'Fees for the current term are non-refundable.'])
        ->assertSessionHasNoErrors();

    expect($refund->refresh()->status)->toBe(RefundStatus::Rejected)
        ->and($refund->reject_reason)->toBe('Fees for the current term are non-refundable.')
        ->and(AuditEvent::query()->where('action', 'fees.refund_rejected')->count())->toBe(1);

    $this->actingAs($this->owner)->post(route('fees.refunds.reject', $refund), ['reason' => 'Rejecting it again'])
        ->assertSessionHasErrors('fees');
});

it('frees the amount again when a refund is rejected', function () {
    $payment = paidInCash($this->invoices[0], $this->riya);
    $refund = requestedRefund($this->riya, 10500, $payment);

    $this->actingAs($this->owner)->post(route('fees.refunds.store'), [
        'student' => $this->riya->ulid, 'payment' => $payment->ulid, 'amount' => 100, 'reason' => 'Fee charged in error',
    ])->assertSessionHasErrors('fees');

    $this->actingAs($this->owner)->post(route('fees.refunds.reject', $refund), ['reason' => 'Parent withdrew the request']);

    requestedRefund($this->riya, 100, $payment);
    expect(FeeRefund::query()->count())->toBe(2);
});

it('applies the authorization matrix to approving a refund', function () {
    $refund = requestedRefund($this->riya, 3500, paidInCash($this->invoices[0], $this->riya));

    $this->assertAuthorizationMatrix(
        module: 'fees',
        permission: 'fees.refund',
        inScope: Scope::sections([$this->main1A->ulid]),
        outOfScope: Scope::sections([$this->north1A->ulid]),
        makeActor: fn (array $permissions, Scope $scope) => refundStaff($permissions, $scope),
        send: fn ($actor) => $this->actingAs($actor)->post(route('fees.refunds.reject', $refund), ['reason' => 'Not refundable']),
    );
});

it('applies the authorization matrix to the refunds screen', function () {
    $this->assertAuthorizationMatrix(
        module: 'fees',
        permission: 'fees.refund',
        inScope: Scope::school(),
        outOfScope: null,
        makeActor: fn (array $permissions, Scope $scope) => refundStaff($permissions, $scope),
        send: fn ($actor) => $this->actingAs($actor)->get(route('fees.refunds.index')),
    );
});

it('shows only the refunds of students in the person\'s scope', function () {
    requestedRefund($this->riya, 100, paidInCash($this->invoices[0], $this->riya));
    $anayaPayment = paidInCash($this->invoicesOf($this->anaya)[0], $this->anaya);
    requestedRefund($this->anaya, 200, $anayaPayment);

    $clerk = refundStaff(['fees.refund'], Scope::sections([$this->north1A->ulid]));

    $this->actingAs($clerk)->get(route('fees.refunds.index', ['tab' => 'all']))
        ->assertInertia(fn (Assert $page) => $page
            ->has('screen.rows', 1)
            ->where('screen.rows.0.student_name', 'Anaya Reddy'));
});

it('keeps one school\'s refunds out of another school', function () {
    requestedRefund($this->riya, 100, paidInCash($this->invoices[0], $this->riya));

    expect(FeeRefund::query()->count())->toBe(1)
        ->and($this->otherSchool->execute(fn () => FeeRefund::query()->count()))->toBe(0);
});
