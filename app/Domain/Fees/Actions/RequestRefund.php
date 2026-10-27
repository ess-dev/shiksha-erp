<?php

namespace App\Domain\Fees\Actions;

use App\Domain\Access\Queries\StaffRefQuery;
use App\Domain\Audit\Data\AuditEntry;
use App\Domain\Fees\Enums\RefundSource;
use App\Domain\Fees\Enums\RefundStatus;
use App\Domain\Fees\Exceptions\FeeException;
use App\Domain\Fees\FeeStudents;
use App\Domain\Fees\FeeVisibility;
use App\Domain\Fees\Models\FeePayment;
use App\Domain\Fees\Models\FeeRefund;
use App\Domain\Fees\RefundLimits;
use App\Domain\Identity\Models\Identity;
use App\Domain\Shared\Action;
use App\Domain\Shared\CalendarDate;
use App\Domain\Shared\Money;
use Illuminate\Support\Str;

class RequestRefund extends Action
{
    public function __construct(
        private readonly FeeVisibility $visibility,
        private readonly FeeStudents $students,
        private readonly StaffRefQuery $staff,
        private readonly RefundLimits $limits,
    ) {}

    /**
     * The office raises a refund request for a student, optionally against
     * one confirmed payment. It cannot ask for more than was received (less
     * refunds already asked for). Needs `fees.refund` over the student.
     */
    public function handle(Identity $actor, string $studentUlid, int $amountPaise, string $reason, ?string $paymentUlid = null): FeeRefund
    {
        $student = $this->students->find($studentUlid) ?? throw FeeException::notFound();
        $this->visibility->assertAllows($actor, $student['id'], 'fees.refund');

        $reason = trim($reason);

        if (mb_strlen($reason) < 5) {
            throw FeeException::detailRequired('a short reason');
        }

        if ($amountPaise <= 0) {
            throw FeeException::amountInvalid('Enter the amount to refund.');
        }

        return $this->transaction(function () use ($actor, $student, $amountPaise, $reason, $paymentUlid) {
            // Locking the student's payments serialises requests, so two clicks cannot both fit under the limit.
            $payments = FeePayment::query()->where('student_id', $student['id'])->orderBy('id')->lockForUpdate()->get();
            $payment = null;

            if ($paymentUlid !== null && $paymentUlid !== '') {
                $payment = $payments->firstWhere('ulid', $paymentUlid) ?? throw FeeException::notFound();
            }

            $left = $payment === null ? $this->limits->forStudent($student['id']) : $this->limits->forPayment($payment);

            if ($amountPaise > $left) {
                throw FeeException::refundTooMuch(Money::paise($left)->format());
            }

            $refund = FeeRefund::query()->create([
                'ulid' => strtolower((string) Str::ulid()),
                'student_id' => $student['id'],
                'fee_payment_id' => $payment?->id,
                'amount_paise' => $amountPaise,
                'reason' => mb_substr($reason, 0, 300),
                'source' => RefundSource::Accounts,
                'requested_by_staff_id' => $this->staff->forIdentity($actor)?->staffKey,
                'requested_on' => CalendarDate::today(),
                'status' => RefundStatus::Pending,
            ]);

            $this->audit(new AuditEntry('fees.refund_requested', 'fee_refund', $refund->ulid, null, [
                'student' => $student['ulid'],
                'amount_paise' => $amountPaise,
                'payment' => $payment?->ulid,
            ]));

            return $refund;
        });
    }
}
