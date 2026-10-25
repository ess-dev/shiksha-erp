<?php

use App\Domain\Fees\Enums\PaymentMode;
use App\Domain\Fees\Enums\PaymentStatus;
use App\Domain\Fees\Models\FeeInvoice;
use App\Domain\Fees\Models\FeePayment;
use App\Domain\Identity\Contracts\OtpSender;
use App\Domain\Students\Data\NewGuardian;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Concerns\BuildsFees;
use Tests\Concerns\BuildsStudents;
use Tests\Concerns\HasCurrentSchool;
use Tests\Concerns\ManagesSchoolPlan;

uses(RefreshDatabase::class, HasCurrentSchool::class, BuildsStudents::class, ManagesSchoolPlan::class, BuildsFees::class);

beforeEach(function () {
    $this->enableModules(['fees']);
    $this->setUpStudentSchool();
    $this->setUpFeeStructure();

    $this->phone = $this->nextPhone();
    $family = fn () => [new NewGuardian('Priya Sharma', 'Mother', $this->phone, null, null, true)];
    $this->riya = $this->makeStudent($this->main1A, 'Riya', 'Sharma', $family());
    $this->arjun = $this->makeStudent($this->main2A, 'Arjun', 'Sharma', $family());
    $this->kabir = $this->makeStudent($this->main1B, 'Kabir', 'Mehta');
    $this->billAllTerms();
    $this->riyaInvoices = $this->invoicesOf($this->riya);

    $this->app->bind(OtpSender::class, fn () => new class implements OtpSender
    {
        public function send(string $phoneE164, string $code, string $schoolName): void
        {
            test()->lastCode = $code;
        }
    });
});

function feesParentSignIn(): void
{
    test()->post(route('parent.send-code'), ['phone' => test()->phone]);
    test()->post(route('parent.verify.store'), ['code' => test()->lastCode])->assertRedirect(route('parent.home'));
}

function counterReceiptFor($invoice, $student): FeePayment
{
    test()->actingAs(test()->owner)->post(route('fees.collect.store'), [
        'student' => $student->ulid, 'invoices' => [$invoice->ulid], 'mode' => 'upi', 'reference' => 'UPI12345678', 'idempotency_key' => (string) Str::uuid(),
    ])->assertSessionHasNoErrors();
    app('auth')->forgetGuards();

    return FeePayment::query()->latest('id')->firstOrFail();
}

it('shows the child\'s installments, totals and what was paid', function () {
    counterReceiptFor($this->riyaInvoices[0], $this->riya);
    feesParentSignIn();

    $this->get(route('parent.fees', ['child' => $this->riya->ulid]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('parent/fees')
            ->where('shell.fees_enabled', true)
            ->where('fees.child_first_name', 'Riya')
            ->where('fees.section_label', 'Class 1-A')
            ->where('fees.session_name', '2026-27')
            ->where('fees.is_current_session', true)
            ->where('fees.has_fees', true)
            ->has('fees.installments', 4)
            ->where('fees.installments.0.label', 'Term 1 (Apr–Jun)')
            ->where('fees.installments.0.status', 'paid')
            ->where('fees.installments.0.balance_paise', 0)
            ->where('fees.installments.0.paid_on', fn ($date) => is_string($date) && strlen($date) === 10)
            ->where('fees.installments.1.balance_paise', 1050000)
            ->where('fees.installments.1.heads.0.name', 'Tuition fee')
            ->where('fees.total_paid_paise', 1050000)
            ->where('fees.total_due_paise', 3150000)
            ->where('fees.total_billed_paise', 4200000)
            ->has('fees.payments', 1)
            ->where('fees.payments.0.mode_label', 'UPI')
            ->where('fees.payments.0.for_label', 'Riya · Term 1')
            ->where('fees.payments.0.receipt.receipt_number', 'RCPT/2026/0001')
            ->where('fees.payments.0.receipt.amount_in_words', 'Rupees Ten Thousand Five Hundred only')
            ->missing('fees.installments.0.id'));
});

it('shows the sibling concession on the second child\'s installments', function () {
    feesParentSignIn();

    $this->get(route('parent.fees', ['child' => $this->arjun->ulid]))
        ->assertInertia(fn (Assert $page) => $page
            ->where('fees.child_first_name', 'Arjun')
            ->where('fees.installments.0.concession_paise', 84000)
            ->where('fees.installments.0.net_paise', 966000)
            ->where('fees.installments.0.concession_note', 'Sibling concession (10% of tuition fee)'));
});

it('warns about a failed payment that is still owed, and about a pending one', function () {
    $this->onlinePayment($this->riya, [$this->riyaInvoices[0]], PaymentStatus::Failed, PaymentMode::Card, 'CRD1');
    $this->onlinePayment($this->riya, [$this->riyaInvoices[1]], PaymentStatus::Pending);
    feesParentSignIn();

    $this->get(route('parent.fees', ['child' => $this->riya->ulid]))
        ->assertInertia(fn (Assert $page) => $page
            ->has('fees.failed_alerts', 1)
            ->where('fees.failed_alerts.0.mode_label', 'Card')
            ->where('fees.failed_alerts.0.amount_paise', 1050000)
            ->where('fees.installments.1.payment_pending', true)
            ->where('fees.installments.0.payment_pending', false)
            ->has('fees.payments', 1)
            ->where('fees.payments.0.status', 'pending'));
});

it('never shows another family\'s child, installments or receipt', function () {
    $otherReceipt = counterReceiptFor($this->invoicesOf($this->kabir)[0], $this->kabir);
    $own = counterReceiptFor($this->riyaInvoices[0], $this->riya);
    feesParentSignIn();

    $this->get(route('parent.fees', ['child' => $this->kabir->ulid]))->assertNotFound();
    $this->get(route('parent.fees.statement', ['child' => $this->kabir->ulid]))->assertNotFound();
    $this->get(route('parent.fees.receipt', [$otherReceipt->ulid, 'child' => $this->riya->ulid]))->assertNotFound();
    $this->get(route('parent.fees.receipt', [$own->ulid, 'child' => $this->arjun->ulid]))->assertNotFound();
});

it('opens a printable receipt and statement for the child', function () {
    $payment = counterReceiptFor($this->riyaInvoices[0], $this->riya);
    feesParentSignIn();

    $this->get(route('parent.fees.receipt', [$payment->ulid, 'child' => $this->riya->ulid]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('parent/fee-receipt')
            ->where('receipt.receipt_number', 'RCPT/2026/0001')
            ->where('receipt.lines.0.student_name', 'Riya Sharma')
            ->where('timezone', 'Asia/Kolkata'));

    $this->get(route('parent.fees.statement', ['child' => $this->riya->ulid]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('parent/fee-statement')
            ->where('school_name', 'Test Public School')
            ->where('fees.total_paid_paise', 1050000));
});

it('has no receipt for a payment that is not confirmed', function () {
    $pending = $this->onlinePayment($this->riya, [$this->riyaInvoices[0]], PaymentStatus::Pending);
    feesParentSignIn();

    $this->get(route('parent.fees.receipt', [$pending->ulid, 'child' => $this->riya->ulid]))->assertNotFound();
});

it('says so when nothing was billed for the viewed session', function () {
    FeeInvoice::query()->where('student_id', $this->riya->id)->delete();
    feesParentSignIn();

    $this->get(route('parent.fees', ['child' => $this->riya->ulid]))
        ->assertInertia(fn (Assert $page) => $page->where('fees.has_fees', false)->has('fees.installments', 0));
});

it('is closed when fees are not in the school\'s plan', function () {
    feesParentSignIn();
    $this->disableModule('fees');

    $this->get(route('parent.fees'))->assertNotFound();
    $this->get(route('parent.home'))->assertInertia(fn (Assert $page) => $page->where('shell.fees_enabled', false));
});
