<?php

use App\Domain\Access\Data\Scope;
use App\Domain\Audit\Models\AuditEvent;
use App\Domain\Fees\Enums\PaymentStatus;
use App\Domain\Fees\Models\FeeInvoice;
use App\Domain\Fees\Models\FeePayment;
use App\Domain\Fees\Models\FeePaymentAllocation;
use App\Domain\School\Models\SchoolSetting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
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
    $this->anaya = $this->makeStudent($this->north1A, 'Anaya', 'Reddy');
    $this->billAllTerms();
    $this->invoices = $this->invoicesOf($this->riya);
});

/**
 * A counter payment request; override what a test changes.
 *
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function counterPayment(array $overrides = []): array
{
    $test = test();

    return array_replace([
        'student' => $test->riya->ulid,
        'invoices' => [$test->invoices[0]->ulid],
        'mode' => 'cash',
        'idempotency_key' => (string) Str::uuid(),
    ], $overrides);
}

it('records a cash payment, applies it, issues a numbered receipt and writes an audit event', function () {
    $response = $this->actingAs($this->owner)->post(route('fees.collect.store'), counterPayment(['invoices' => [$this->invoices[0]->ulid, $this->invoices[1]->ulid]]));

    $response->assertRedirect()->assertSessionHasNoErrors();

    $payment = FeePayment::query()->firstOrFail();

    expect($payment->status)->toBe(PaymentStatus::Success)
        ->and($payment->amount_paise)->toBe(2100000)
        ->and($payment->receipt_number)->toBe('RCPT/2026/0001')
        ->and($payment->channel->value)->toBe('counter')
        ->and($payment->received_from)->toBe('Riya Parent Sharma')
        ->and($payment->collected_by_staff_id)->not->toBeNull()
        ->and($this->invoices[0]->fresh()->paid_paise)->toBe(1050000)
        ->and($this->invoices[1]->fresh()->paid_paise)->toBe(1050000)
        ->and($this->invoices[2]->fresh()->paid_paise)->toBe(0)
        ->and(FeePaymentAllocation::query()->where('fee_payment_id', $payment->id)->count())->toBe(2);

    $event = AuditEvent::query()->where('action', 'fees.payment_recorded')->firstOrFail();
    expect($event->subject_ulid)->toBe($payment->ulid)
        ->and($event->after['receipt_number'])->toBe('RCPT/2026/0001')
        ->and($event->after['amount_paise'])->toBe(2100000);
});

it('numbers receipts one after another, with the school\'s own prefix', function () {
    SchoolSetting::query()->create(['key' => 'fees.receipt_prefix', 'value' => 'GPS']);

    foreach ([0, 1] as $position) {
        $this->actingAs($this->owner)->post(route('fees.collect.store'), counterPayment(['invoices' => [$this->invoices[$position]->ulid]]))->assertSessionHasNoErrors();
    }

    expect(FeePayment::query()->orderBy('id')->pluck('receipt_number')->all())->toBe(['GPS/2026/0001', 'GPS/2026/0002']);
});

it('takes a part payment and applies it to the oldest installment first', function () {
    $this->actingAs($this->owner)->post(route('fees.collect.store'), counterPayment([
        'invoices' => [$this->invoices[0]->ulid, $this->invoices[1]->ulid],
        'amount' => 15000,
    ]))->assertSessionHasNoErrors();

    expect($this->invoices[0]->fresh()->paid_paise)->toBe(1050000)
        ->and($this->invoices[1]->fresh()->paid_paise)->toBe(450000)
        ->and($this->invoices[1]->fresh()->balancePaise())->toBe(600000)
        ->and(FeePayment::query()->firstOrFail()->amount_paise)->toBe(1500000);
});

it('keeps balances exact when an amount covers less than the first installment', function () {
    $this->actingAs($this->owner)->post(route('fees.collect.store'), counterPayment(['amount' => 5000]))->assertSessionHasNoErrors();
    $this->actingAs($this->owner)->post(route('fees.collect.store'), counterPayment(['amount' => 5500]))->assertSessionHasNoErrors();

    expect($this->invoices[0]->fresh()->paid_paise)->toBe(1050000)
        ->and($this->invoices[0]->fresh()->balancePaise())->toBe(0);

    $this->actingAs($this->owner)->post(route('fees.collect.store'), counterPayment())->assertSessionHasErrors('fees');
});

it('refuses an amount larger than what is selected, or no amount, or nothing selected', function (array $overrides, string $key) {
    $this->actingAs($this->owner)->post(route('fees.collect.store'), counterPayment($overrides))->assertSessionHasErrors($key);

    expect(FeePayment::query()->count())->toBe(0)
        ->and(FeeInvoice::query()->sum('paid_paise'))->toBe(0);
})->with([
    'more than selected' => [['amount' => 10501], 'fees'],
    'zero' => [['amount' => 0], 'amount'],
    'negative' => [['amount' => -5], 'amount'],
    'fractional' => [['amount' => 10.5], 'amount'],
    'no installments' => [['invoices' => []], 'invoices'],
    'unknown mode' => [['mode' => 'barter'], 'mode'],
    'net banking at the counter' => [['mode' => 'net_banking'], 'mode'],
]);

it('records one payment however many times the same request arrives', function () {
    $request = counterPayment();

    $this->actingAs($this->owner)->post(route('fees.collect.store'), $request)->assertSessionHasNoErrors();
    $this->actingAs($this->owner)->post(route('fees.collect.store'), $request)->assertSessionHasNoErrors();

    expect(FeePayment::query()->count())->toBe(1)
        ->and($this->invoices[0]->fresh()->paid_paise)->toBe(1050000)
        ->and(AuditEvent::query()->where('action', 'fees.payment_recorded')->count())->toBe(1);
});

it('does not accept another student\'s installment or a repeated key for another student', function () {
    $other = $this->invoicesOf($this->anaya)[0];

    $this->actingAs($this->owner)->post(route('fees.collect.store'), counterPayment(['invoices' => [$other->ulid]]))->assertNotFound();

    $key = (string) Str::uuid();
    $this->actingAs($this->owner)->post(route('fees.collect.store'), counterPayment(['idempotency_key' => $key]))->assertSessionHasNoErrors();
    $this->actingAs($this->owner)->post(route('fees.collect.store'), counterPayment(['student' => $this->anaya->ulid, 'invoices' => [$other->ulid], 'idempotency_key' => $key]))->assertNotFound();

    expect(FeePayment::query()->count())->toBe(1);
});

it('asks for the details a mode needs', function (array $overrides, string $message) {
    $this->actingAs($this->owner)->post(route('fees.collect.store'), counterPayment($overrides))
        ->assertSessionHasErrors(['fees' => $message]);

    expect(FeePayment::query()->count())->toBe(0);
})->with([
    'UPI needs a reference' => [['mode' => 'upi'], 'Enter the transaction reference.'],
    'card needs a reference' => [['mode' => 'card', 'reference' => '  '], 'Enter the transaction reference.'],
    'cheque needs a number' => [['mode' => 'cheque', 'bank' => 'SBI', 'cheque_date' => '2026-10-20'], 'Enter the cheque number.'],
    'cheque needs a bank' => [['mode' => 'cheque', 'cheque_number' => '123456', 'cheque_date' => '2026-10-20'], 'Enter the bank.'],
    'cheque needs a date' => [['mode' => 'cheque', 'cheque_number' => '123456', 'bank' => 'SBI'], 'Enter the cheque date.'],
]);

it('keeps a cheque pending until it clears, then confirms it with a receipt', function () {
    $this->actingAs($this->owner)->post(route('fees.collect.store'), counterPayment([
        'mode' => 'cheque', 'cheque_number' => '123456', 'bank' => 'State Bank of India', 'cheque_date' => '2026-10-20',
    ]))->assertSessionHasNoErrors();

    $payment = FeePayment::query()->firstOrFail();
    expect($payment->status)->toBe(PaymentStatus::Pending)
        ->and($payment->receipt_number)->toBeNull()
        ->and($payment->reference)->toBe('123456')
        ->and($this->invoices[0]->fresh()->paid_paise)->toBe(0);

    $this->actingAs($this->owner)->post(route('fees.payments.cheque', $payment), ['cleared' => true])->assertSessionHasNoErrors();

    $payment->refresh();
    expect($payment->status)->toBe(PaymentStatus::Success)
        ->and($payment->receipt_number)->toBe('RCPT/2026/0001')
        ->and($payment->settled_at)->not->toBeNull()
        ->and($this->invoices[0]->fresh()->paid_paise)->toBe(1050000);

    // Clearing twice does nothing more.
    $this->actingAs($this->owner)->post(route('fees.payments.cheque', $payment), ['cleared' => true])->assertSessionHasErrors('fees');
    expect($this->invoices[0]->fresh()->paid_paise)->toBe(1050000);
});

it('marks a bounced cheque failed with the bank\'s reason and leaves the installment unpaid', function () {
    $this->actingAs($this->owner)->post(route('fees.collect.store'), counterPayment([
        'mode' => 'cheque', 'cheque_number' => '123456', 'bank' => 'HDFC Bank', 'cheque_date' => '2026-10-20',
    ]));
    $payment = FeePayment::query()->firstOrFail();

    $this->actingAs($this->owner)->post(route('fees.payments.cheque', $payment), ['cleared' => false])->assertSessionHasErrors('reason');
    $this->actingAs($this->owner)->post(route('fees.payments.cheque', $payment), ['cleared' => false, 'reason' => 'Insufficient funds'])->assertSessionHasNoErrors();

    $payment->refresh();
    expect($payment->status)->toBe(PaymentStatus::Failed)
        ->and($payment->failure_reason)->toBe('Cheque bounced: insufficient funds')
        ->and($payment->receipt_number)->toBeNull()
        ->and($this->invoices[0]->fresh()->paid_paise)->toBe(0);
});

it('never over-credits when an installment was paid another way before a cheque cleared', function () {
    $this->actingAs($this->owner)->post(route('fees.collect.store'), counterPayment([
        'mode' => 'cheque', 'cheque_number' => '123456', 'bank' => 'SBI', 'cheque_date' => '2026-10-20',
    ]));
    $cheque = FeePayment::query()->firstOrFail();

    $this->actingAs($this->owner)->post(route('fees.collect.store'), counterPayment())->assertSessionHasNoErrors();
    expect($this->invoices[0]->fresh()->paid_paise)->toBe(1050000);

    $this->actingAs($this->owner)->post(route('fees.payments.cheque', $cheque), ['cleared' => true])->assertSessionHasNoErrors();

    $cheque->refresh();
    expect($cheque->status)->toBe(PaymentStatus::Success)
        ->and($cheque->receipt_number)->toBeNull()
        ->and($cheque->details['excess_paise'])->toBe(1050000)
        ->and($this->invoices[0]->fresh()->paid_paise)->toBe(1050000)
        ->and(FeePaymentAllocation::query()->where('fee_payment_id', $cheque->id)->count())->toBe(0);
});

it('refuses to collect for an installment that is already paid', function () {
    $this->actingAs($this->owner)->post(route('fees.collect.store'), counterPayment());
    $this->actingAs($this->owner)->post(route('fees.collect.store'), counterPayment())->assertSessionHasErrors('fees');

    expect(FeePayment::query()->count())->toBe(1);
});

it('searches students the person may collect for and shows what is due', function () {
    $this->actingAs($this->owner)->get(route('fees.collect.index', ['q' => 'riy']))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('fees/collect')
            ->where('screen.has_schedule', true)
            ->has('screen.results', 1)
            ->where('screen.results.0.name', 'Riya Sharma')
            ->where('screen.results.0.section_label', 'Class 1-A')
            ->has('screen.modes', 5)
            ->where('screen.student', null));

    $this->actingAs($this->owner)->get(route('fees.collect.index', ['q' => 'r']))
        ->assertInertia(fn (Assert $page) => $page->has('screen.results', 0));

    $this->actingAs($this->owner)->get(route('fees.collect.index', ['q' => '1-A']))
        ->assertInertia(fn (Assert $page) => $page
            ->has('screen.results', 2)
            ->where('screen.results.0.name', 'Anaya Reddy')
            ->where('screen.results.1.name', 'Riya Sharma'));
});

it('lists the chosen student\'s open installments, oldest first, with a note about pending payments', function () {
    $this->onlinePayment($this->riya, [$this->invoices[1]], PaymentStatus::Pending);
    $this->invoices[0]->forceFill(['paid_paise' => 250000])->save();

    $this->actingAs($this->owner)->get(route('fees.collect.index', ['student' => $this->riya->ulid]))
        ->assertInertia(fn (Assert $page) => $page
            ->where('screen.student.name', 'Riya Sharma')
            ->where('screen.student.guardian_name', 'Riya Parent Sharma')
            ->where('screen.student.guardian_relation', 'Mother')
            ->has('screen.student.installments', 4)
            ->where('screen.student.installments.0.balance_paise', 800000)
            ->where('screen.student.installments.0.paid_paise', 250000)
            ->where('screen.student.installments.1.pending_note', 'Net banking payment of ₹10,500 is still pending. Check before collecting again.'));
});

it('does not show a student out of reach', function () {
    $mainOnly = $this->staffWith(['fees.collect'], Scope::campuses([$this->main->ulid]));

    $this->actingAs($mainOnly)->get(route('fees.collect.index', ['student' => $this->anaya->ulid, 'q' => 'anaya']))
        ->assertInertia(fn (Assert $page) => $page->where('screen.student', null)->has('screen.results', 0));
});

it('applies the authorization matrix to collecting a fee', function () {
    $this->assertAuthorizationMatrix(
        module: 'fees',
        permission: 'fees.collect',
        inScope: Scope::sections([$this->main1A->ulid]),
        outOfScope: Scope::sections([$this->north1A->ulid]),
        makeActor: fn (array $permissions, Scope $scope) => $this->staffWith($permissions, $scope),
        send: fn ($actor) => $this->actingAs($actor)->post(route('fees.collect.store'), counterPayment(['amount' => 1])),
    );
});

it('applies the authorization matrix to the collect screen', function () {
    $this->assertAuthorizationMatrix(
        module: 'fees',
        permission: 'fees.collect',
        inScope: Scope::school(),
        outOfScope: null,
        makeActor: fn (array $permissions, Scope $scope) => $this->staffWith($permissions, $scope),
        send: fn ($actor) => $this->actingAs($actor)->get(route('fees.collect.index')),
    );
});
