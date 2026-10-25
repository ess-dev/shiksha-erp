<?php

namespace App\Domain\Fees\Actions;

use App\Domain\Access\Queries\StaffRefQuery;
use App\Domain\Audit\Data\AuditEntry;
use App\Domain\Fees\CounterPayment;
use App\Domain\Fees\Enums\PaymentChannel;
use App\Domain\Fees\Enums\PaymentMode;
use App\Domain\Fees\Enums\PaymentStatus;
use App\Domain\Fees\Exceptions\FeeException;
use App\Domain\Fees\FeeStudents;
use App\Domain\Fees\FeeVisibility;
use App\Domain\Fees\Models\FeeInvoice;
use App\Domain\Fees\Models\FeePayment;
use App\Domain\Fees\Models\FeePaymentAllocation;
use App\Domain\Fees\PaymentSettlement;
use App\Domain\Identity\Models\Identity;
use App\Domain\School\Queries\AcademicSessionsQuery;
use App\Domain\Shared\Action;
use App\Domain\Shared\CalendarDate;
use App\Domain\Shared\Money;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Str;

class RecordCounterPayment extends Action
{
    public function __construct(
        private readonly FeeVisibility $visibility,
        private readonly FeeStudents $students,
        private readonly StaffRefQuery $staff,
        private readonly PaymentSettlement $settlement,
        private readonly AcademicSessionsQuery $years,
    ) {}

    /**
     * Records money taken at the counter for some installments of one student
     * and applies it, oldest due date first. Cash, UPI, card and bank transfer
     * are confirmed at once (a receipt is issued); a cheque waits as pending
     * until it clears. The same idempotency key never records a second payment.
     * Needs `fees.collect` over the student's section.
     */
    public function handle(Identity $actor, CounterPayment $input): FeePayment
    {
        $student = $this->students->find($input->studentUlid) ?? throw FeeException::notFound();
        $this->visibility->assertAllows($actor, $student['id'], 'fees.collect');

        $existing = FeePayment::query()->where('idempotency_key', $input->idempotencyKey)->first();

        if ($existing !== null) {
            if ($existing->student_id !== $student['id']) {
                throw FeeException::notFound();
            }

            return $existing;
        }

        if (! $input->mode->isCounterMode()) {
            throw FeeException::amountInvalid('Choose how the money was received.');
        }

        $this->checkDetails($input);

        if ($input->invoiceUlids === []) {
            throw FeeException::nothingSelected();
        }

        $year = $this->years->find() ?? throw FeeException::noAcademicYear();

        try {
            return $this->transaction(function () use ($actor, $input, $student, $year) {
                $invoices = FeeInvoice::query()
                    ->where('student_id', $student['id'])
                    ->whereIn('ulid', array_values(array_unique($input->invoiceUlids)))
                    ->orderBy('due_on')->orderBy('id')
                    ->lockForUpdate()
                    ->get();

                if ($invoices->count() !== count(array_unique($input->invoiceUlids))) {
                    throw FeeException::notFound();
                }

                $open = $invoices->filter(fn (FeeInvoice $invoice) => $invoice->balancePaise() > 0)->values();

                if ($open->isEmpty()) {
                    throw FeeException::alreadyPaid();
                }

                $total = Money::paise((int) $open->sum(fn (FeeInvoice $invoice) => $invoice->balancePaise()));
                $amount = $input->amountPaise === null ? $total : Money::paise($input->amountPaise);

                if (! $amount->isPositive()) {
                    throw FeeException::amountInvalid('Enter an amount to collect.');
                }

                if ($amount->greaterThan($total)) {
                    throw FeeException::amountInvalid('That is more than the '.$total->format().' selected.');
                }

                $payment = FeePayment::query()->create([
                    'ulid' => strtolower((string) Str::ulid()),
                    'student_id' => $student['id'],
                    'mode' => $input->mode,
                    'status' => PaymentStatus::Pending,
                    'channel' => PaymentChannel::Counter,
                    'amount_paise' => $amount->paise,
                    'paid_at' => now(),
                    'reference' => $this->clean($input->mode === PaymentMode::Cheque ? $input->chequeNumber : $input->reference, 60),
                    'gateway' => null,
                    'collected_by_staff_id' => $this->staff->forIdentity($actor)?->staffKey,
                    'received_from' => $this->clean($input->receivedFrom, 120) ?? $this->students->primaryGuardianName($student['id']),
                    'note' => $this->clean($input->note, 300),
                    'details' => $input->mode === PaymentMode::Cheque
                        ? ['cheque_number' => $this->clean($input->chequeNumber, 40), 'bank' => $this->clean($input->bank, 60), 'cheque_date' => $input->chequeDate]
                        : null,
                    'idempotency_key' => $input->idempotencyKey,
                ]);

                $left = $amount;

                foreach ($open as $invoice) {
                    if (! $left->isPositive()) {
                        break;
                    }

                    $take = Money::paise($invoice->balancePaise())->atMost($left);

                    FeePaymentAllocation::query()->create([
                        'fee_payment_id' => $payment->id,
                        'fee_invoice_id' => $invoice->id,
                        'amount_paise' => $take->paise,
                    ]);

                    $left = $left->minus($take);
                }

                // A cheque is not money until it clears; everything else is confirmed now.
                if ($input->mode !== PaymentMode::Cheque) {
                    $this->settlement->confirm($payment, $year['starts_on']);
                }

                $payment->refresh();

                $this->audit(new AuditEntry('fees.payment_recorded', 'fee_payment', $payment->ulid, null, [
                    'student' => $student['ulid'],
                    'mode' => $input->mode->value,
                    'amount_paise' => $payment->amount_paise,
                    'status' => $payment->status->value,
                    'receipt_number' => $payment->receipt_number,
                    'installments' => $open->count(),
                ]));

                return $payment;
            });
        } catch (UniqueConstraintViolationException) {
            // Two clicks at once: the other request recorded it first.
            return FeePayment::query()->where('idempotency_key', $input->idempotencyKey)->firstOrFail();
        }
    }

    private function checkDetails(CounterPayment $input): void
    {
        if ($input->mode === PaymentMode::Cheque) {
            if ($this->clean($input->chequeNumber, 40) === null) {
                throw FeeException::detailRequired('the cheque number');
            }

            if ($this->clean($input->bank, 60) === null) {
                throw FeeException::detailRequired('the bank');
            }

            if ($input->chequeDate === null || preg_match('/^\d{4}-\d{2}-\d{2}$/', $input->chequeDate) !== 1) {
                throw FeeException::detailRequired('the cheque date');
            }

            CalendarDate::parse($input->chequeDate);

            return;
        }

        if ($input->mode !== PaymentMode::Cash && $this->clean($input->reference, 60) === null) {
            throw FeeException::detailRequired('the transaction reference');
        }
    }

    private function clean(?string $value, int $max): ?string
    {
        $value = trim((string) $value);

        return $value === '' ? null : mb_substr($value, 0, $max);
    }
}
