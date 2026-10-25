<?php

use App\Domain\Access\Data\Scope;
use App\Domain\Audit\Models\AuditEvent;
use App\Domain\Fees\Contracts\FeeNotifier;
use App\Domain\Fees\Enums\PaymentMode;
use App\Domain\Fees\Enums\PaymentStatus;
use App\Domain\Fees\Models\FeePayment;
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
    $this->useGateway('fake');
});

/** A confirmed counter payment for the given installment. */
function cashFor($invoice, $student): FeePayment
{
    test()->actingAs(test()->owner)->post(route('fees.collect.store'), [
        'student' => $student->ulid, 'invoices' => [$invoice->ulid], 'mode' => 'cash', 'idempotency_key' => (string) Str::uuid(),
    ])->assertSessionHasNoErrors();

    return FeePayment::query()->latest('id')->firstOrFail();
}

it('lists payments with what needs action first, then newest', function () {
    $cash = cashFor($this->invoices[0], $this->riya);
    $pending = $this->onlinePayment($this->riya, [$this->invoices[1]], PaymentStatus::Pending);
    $failed = $this->onlinePayment($this->riya, [$this->invoices[2]], PaymentStatus::Failed, PaymentMode::Card, 'CRD289312629');

    $this->actingAs($this->owner)->get(route('fees.payments.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('fees/payments')
            ->where('timezone', 'Asia/Kolkata')
            ->where('screen.gateway.label', 'Test gateway (no real money)')
            ->where('screen.gateway.simulated', true)
            ->has('screen.rows', 3)
            ->where('screen.rows.0.ulid', $pending->ulid)
            ->where('screen.rows.0.can_check', true)
            ->where('screen.rows.0.status_note', 'Awaiting gateway confirmation')
            ->where('screen.rows.1.ulid', $failed->ulid)
            ->where('screen.rows.1.can_recheck', true)
            ->where('screen.rows.1.can_send_link', true)
            ->where('screen.rows.1.status_note', 'Issuer declined the transaction')
            ->where('screen.rows.2.ulid', $cash->ulid)
            ->where('screen.rows.2.receipt.receipt_number', 'RCPT/2026/0001')
            ->where('screen.rows.2.receipt.amount_in_words', 'Rupees Ten Thousand Five Hundred only')
            ->where('screen.rows.2.is_counter', true)
            ->where('screen.rows.2.detail.lines.0.student_name', 'Riya Sharma')
            ->where('screen.rows.2.detail.lines.0.fully_paid', true)
            ->where('screen.summary.successful_paise', 1050000)
            ->where('screen.summary.successful_count', 1)
            ->where('screen.summary.pending_count', 1)
            ->where('screen.summary.pending_paise', 1050000)
            ->where('screen.summary.failed_count', 1)
            ->where('screen.summary.failed_paise', 1050000));
});

it('stops calling a failed payment unresolved once the family has paid another way', function () {
    $failed = $this->onlinePayment($this->riya, [$this->invoices[0]], PaymentStatus::Failed, PaymentMode::Card, 'CRD1');
    cashFor($this->invoices[0], $this->riya);

    $this->actingAs($this->owner)->get(route('fees.payments.index', ['status' => 'failed']))
        ->assertInertia(fn (Assert $page) => $page
            ->where('screen.rows.0.ulid', $failed->ulid)
            ->where('screen.rows.0.can_recheck', false)
            ->where('screen.rows.0.can_send_link', false)
            ->where('screen.rows.0.status_note', 'Paid separately later')
            ->where('screen.summary.failed_count', 0));
});

it('filters by status, mode, dates and search text', function () {
    $cash = cashFor($this->invoices[0], $this->riya);
    $this->onlinePayment($this->anaya, [$this->invoicesOf($this->anaya)[0]], PaymentStatus::Pending, PaymentMode::Upi, 'UPI536756376');

    $rows = fn (array $query) => collect($this->actingAs($this->owner)->get(route('fees.payments.index', $query))->viewData('page')['props']['screen']['rows']);

    expect($rows(['status' => 'success']))->toHaveCount(1)
        ->and($rows(['status' => 'pending']))->toHaveCount(1)
        ->and($rows(['mode' => 'upi']))->toHaveCount(1)
        ->and($rows(['mode' => 'cash'])->first()['ulid'])->toBe($cash->ulid)
        ->and($rows(['q' => 'RCPT/2026/0001']))->toHaveCount(1)
        ->and($rows(['q' => 'upi5367']))->toHaveCount(1)
        ->and($rows(['q' => 'anaya']))->toHaveCount(1)
        ->and($rows(['from' => '2099-01-01']))->toHaveCount(0)
        ->and($rows(['to' => '2000-01-01']))->toHaveCount(0)
        ->and($rows(['from' => '2000-01-01', 'to' => '2099-01-01']))->toHaveCount(2);
});

it('shows only the payments of students the person may see', function () {
    cashFor($this->invoices[0], $this->riya);
    $this->onlinePayment($this->anaya, [$this->invoicesOf($this->anaya)[0]], PaymentStatus::Pending);
    $north = $this->staffWith(['fees.view'], Scope::campuses([$this->north->ulid]));

    $this->actingAs($north)->get(route('fees.payments.index'))
        ->assertInertia(fn (Assert $page) => $page
            ->has('screen.rows', 1)
            ->where('screen.rows.0.student_name', 'Anaya Reddy')
            ->where('screen.summary.successful_count', 0)
            ->where('screen.rows.0.can_check', false));
});

it('confirms a pending online payment when the gateway says it was captured', function () {
    $pending = $this->onlinePayment($this->riya, [$this->invoices[0]], PaymentStatus::Pending);

    $this->actingAs($this->owner)->post(route('fees.payments.check', $pending), ['report' => 'captured'])->assertSessionHasNoErrors();

    $pending->refresh();
    expect($pending->status)->toBe(PaymentStatus::Success)
        ->and($pending->receipt_number)->toBe('RCPT/2026/0001')
        ->and($pending->recovered_on_recheck)->toBeFalse()
        ->and($this->invoices[0]->fresh()->paid_paise)->toBe(1050000)
        ->and(AuditEvent::query()->where('action', 'fees.payment_checked')->firstOrFail()->after['gateway_report'])->toBe('captured');
});

it('marks a pending online payment failed when the gateway says so, and leaves it pending when it cannot say yet', function () {
    $pending = $this->onlinePayment($this->riya, [$this->invoices[0]], PaymentStatus::Pending);

    $this->actingAs($this->owner)->post(route('fees.payments.check', $pending), [])->assertSessionHasNoErrors();
    expect($pending->fresh()->status)->toBe(PaymentStatus::Pending);

    $this->actingAs($this->owner)->post(route('fees.payments.check', $pending), ['report' => 'failed'])->assertSessionHasNoErrors();

    $pending->refresh();
    expect($pending->status)->toBe(PaymentStatus::Failed)
        ->and($pending->failure_reason)->toBe('The gateway reported the payment as failed')
        ->and($this->invoices[0]->fresh()->paid_paise)->toBe(0);
});

it('recovers a failed payment the bank had actually captured', function () {
    $failed = $this->onlinePayment($this->riya, [$this->invoices[0]], PaymentStatus::Failed, PaymentMode::Card, 'CRD1');

    $this->actingAs($this->owner)->post(route('fees.payments.recheck', $failed), ['report' => 'failed'])->assertSessionHasNoErrors();
    expect($failed->fresh()->status)->toBe(PaymentStatus::Failed);

    $this->actingAs($this->owner)->post(route('fees.payments.recheck', $failed), ['report' => 'captured'])->assertSessionHasNoErrors();

    $failed->refresh();
    expect($failed->status)->toBe(PaymentStatus::Success)
        ->and($failed->recovered_on_recheck)->toBeTrue()
        ->and($failed->failure_reason)->toBeNull()
        ->and($failed->receipt_number)->not->toBeNull()
        ->and($this->invoices[0]->fresh()->paid_paise)->toBe(1050000);
});

it('does not apply a captured payment twice when the family paid the installment meanwhile', function () {
    $pending = $this->onlinePayment($this->riya, [$this->invoices[0]], PaymentStatus::Pending);
    cashFor($this->invoices[0], $this->riya);

    $this->actingAs($this->owner)->post(route('fees.payments.check', $pending), ['report' => 'captured'])->assertSessionHasNoErrors();

    $pending->refresh();
    expect($pending->status)->toBe(PaymentStatus::Success)
        ->and($pending->receipt_number)->toBeNull()
        ->and($pending->details['excess_paise'])->toBe(1050000)
        ->and($this->invoices[0]->fresh()->paid_paise)->toBe(1050000);

    $this->actingAs($this->owner)->get(route('fees.payments.index', ['status' => 'success']))
        ->assertInertia(fn (Assert $page) => $page->where('screen.summary.successful_paise', 1050000));
});

it('refuses to check when the school has no gateway, the payment was taken at the counter, or it is not in the right state', function () {
    $pending = $this->onlinePayment($this->riya, [$this->invoices[0]], PaymentStatus::Pending);
    $cash = cashFor($this->invoices[1], $this->riya);

    $this->actingAs($this->owner)->post(route('fees.payments.check', $cash), ['report' => 'captured'])->assertSessionHasErrors('fees');
    $this->actingAs($this->owner)->post(route('fees.payments.recheck', $pending), ['report' => 'captured'])->assertSessionHasErrors('fees');

    $this->useGateway(null);
    $this->actingAs($this->owner)->post(route('fees.payments.check', $pending), ['report' => 'captured'])
        ->assertSessionHasErrors(['fees' => 'No payment gateway is set up for this school. Ask the platform administrator to choose one.']);

    expect($pending->fresh()->status)->toBe(PaymentStatus::Pending);
});

it('does not use a gateway that is not allowed in this environment', function () {
    $pending = $this->onlinePayment($this->riya, [$this->invoices[0]], PaymentStatus::Pending);

    config(['shiksha.payment_gateways.fake.environments' => ['production']]);

    $this->actingAs($this->owner)->post(route('fees.payments.check', $pending), ['report' => 'captured'])->assertSessionHasErrors('fees');

    expect($pending->fresh()->status)->toBe(PaymentStatus::Pending);
});

it('sends a payment link for a failed payment that is still owed, and records it', function () {
    $this->mock(FeeNotifier::class)->shouldReceive('sendPaymentLink')->once();
    $failed = $this->onlinePayment($this->riya, [$this->invoices[0]], PaymentStatus::Failed, PaymentMode::Card, 'CRD1');

    $this->actingAs($this->owner)->post(route('fees.payments.link', $failed))->assertSessionHasNoErrors();

    expect($failed->fresh()->link_sent_at)->not->toBeNull()
        ->and(AuditEvent::query()->where('action', 'fees.payment_link_sent')->count())->toBe(1);
});

it('does not send a payment link for a payment that is not failed or is already settled', function () {
    $pending = $this->onlinePayment($this->riya, [$this->invoices[0]], PaymentStatus::Pending);
    $this->actingAs($this->owner)->post(route('fees.payments.link', $pending))->assertSessionHasErrors('fees');
    expect($pending->fresh()->link_sent_at)->toBeNull();
});

it('opens a receipt for a confirmed payment and nothing else', function () {
    $cash = cashFor($this->invoices[0], $this->riya);
    $pending = $this->onlinePayment($this->riya, [$this->invoices[1]], PaymentStatus::Pending);

    $this->actingAs($this->owner)->get(route('fees.receipts.show', $cash))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('fees/receipt')
            ->where('receipt.receipt_number', 'RCPT/2026/0001')
            ->where('receipt.school_name', 'Test Public School')
            ->where('receipt.total_paise', 1050000)
            ->where('receipt.mode_label', 'Cash')
            ->where('receipt.is_counter', true)
            ->where('receipt.academic_year', '2026-27')
            ->where('receipt.lines.0.installment_label', 'Term 1 (Apr–Jun)'));

    $this->actingAs($this->owner)->get(route('fees.receipts.show', $pending))->assertNotFound();
});

it('does not open the receipt of a student out of reach', function () {
    $cash = cashFor($this->invoicesOf($this->anaya)[0], $this->anaya);
    $mainOnly = $this->staffWith(['fees.view'], Scope::campuses([$this->main->ulid]));

    $this->actingAs($mainOnly)->get(route('fees.receipts.show', $cash))->assertNotFound();
});

it('exports the filtered payments as an audited CSV', function () {
    cashFor($this->invoices[0], $this->riya);
    $this->onlinePayment($this->anaya, [$this->invoicesOf($this->anaya)[0]], PaymentStatus::Pending, PaymentMode::Upi, 'UPI536756376');

    $csv = $this->actingAs($this->owner)->get(route('fees.payments.export', ['status' => 'success']))->streamedContent();
    $rows = array_map(fn (string $line) => str_getcsv($line, ',', '"', ''), array_values(array_filter(explode("\n", ltrim($csv, "\xEF\xBB\xBF")))));

    expect($rows[0])->toBe(['Date', 'Time', 'Payment ID', 'Student', 'Class', 'Mode', 'Channel', 'Reference', 'Amount (₹)', 'Status', 'Receipt', 'Note'])
        ->and($rows)->toHaveCount(2)
        ->and($rows[1][3])->toBe('Riya Sharma')
        ->and($rows[1][5])->toBe('Cash')
        ->and($rows[1][6])->toBe('Counter')
        ->and($rows[1][8])->toBe('10500')
        ->and($rows[1][10])->toBe('RCPT/2026/0001')
        ->and($rows[1][11])->toBe('')
        ->and($csv)->not->toContain('Anaya Reddy');

    $event = AuditEvent::query()->where('action', 'fees.payments_exported')->firstOrFail();
    expect($event->after['rows'])->toBe(1)->and($event->after['status'])->toBe('success');
});

it('applies the authorization matrix to the payments screen', function () {
    $this->assertAuthorizationMatrix(
        module: 'fees',
        permission: 'fees.view',
        inScope: Scope::school(),
        outOfScope: null,
        makeActor: fn (array $permissions, Scope $scope) => $this->staffWith($permissions, $scope),
        send: fn ($actor) => $this->actingAs($actor)->get(route('fees.payments.index')),
    );
});

it('applies the authorization matrix to checking a payment with the gateway', function () {
    $pending = $this->onlinePayment($this->riya, [$this->invoices[0]], PaymentStatus::Pending);

    $this->assertAuthorizationMatrix(
        module: 'fees',
        permission: 'fees.reconcile',
        inScope: Scope::sections([$this->main1A->ulid]),
        outOfScope: Scope::sections([$this->north1A->ulid]),
        makeActor: fn (array $permissions, Scope $scope) => $this->staffWith($permissions, $scope),
        send: fn ($actor) => $this->actingAs($actor)->post(route('fees.payments.check', $pending), []),
    );
});

it('applies the authorization matrix to sending a payment link', function () {
    $this->mock(FeeNotifier::class)->shouldReceive('sendPaymentLink');
    $failed = $this->onlinePayment($this->riya, [$this->invoices[0]], PaymentStatus::Failed, PaymentMode::Card, 'CRD1');

    $this->assertAuthorizationMatrix(
        module: 'fees',
        permission: 'fees.remind',
        inScope: Scope::sections([$this->main1A->ulid]),
        outOfScope: Scope::sections([$this->north1A->ulid]),
        makeActor: fn (array $permissions, Scope $scope) => $this->staffWith($permissions, $scope),
        send: fn ($actor) => $this->actingAs($actor)->post(route('fees.payments.link', $failed)),
    );
});
