<?php

namespace App\Domain\Fees\Actions;

use App\Domain\Access\Queries\StaffRefQuery;
use App\Domain\Audit\Data\AuditEntry;
use App\Domain\Fees\Contracts\FeeNotifier;
use App\Domain\Fees\Enums\RefundMethod;
use App\Domain\Fees\Enums\RefundStatus;
use App\Domain\Fees\Exceptions\FeeException;
use App\Domain\Fees\FeeVisibility;
use App\Domain\Fees\Models\FeeInvoice;
use App\Domain\Fees\Models\FeeRefund;
use App\Domain\Identity\Models\Identity;
use App\Domain\School\Queries\SchoolSettingsQuery;
use App\Domain\Shared\Action;
use App\Domain\Shared\Money;

class ApproveRefund extends Action
{
    public function __construct(
        private readonly FeeVisibility $visibility,
        private readonly StaffRefQuery $staff,
        private readonly SchoolSettingsQuery $settings,
        private readonly FeeNotifier $notifier,
    ) {}

    /**
     * Approves a pending refund and records how it reaches the family. Above
     * the school's sign-off limit the person must confirm the principal has
     * signed off. "Adjust against next term" is credited at once to the
     * student's unpaid installments, oldest due date first (all of the amount
     * or none); the other ways only record the payout, the money moves
     * outside the app. Needs `fees.refund` over the student.
     */
    public function handle(Identity $actor, FeeRefund $refund, RefundMethod $method, ?string $reference, bool $principalSignedOff): FeeRefund
    {
        $this->visibility->assertAllows($actor, $refund->student_id, 'fees.refund');

        $refund = $this->transaction(function () use ($actor, $refund, $method, $reference, $principalSignedOff) {
            $refund = FeeRefund::query()->whereKey($refund->id)->lockForUpdate()->firstOrFail();

            if ($refund->status !== RefundStatus::Pending) {
                throw FeeException::refundNotPending();
            }

            $limit = $this->settings->refundPrincipalLimitPaise();
            $overLimit = $refund->amount_paise > $limit;

            if ($overLimit && ! $principalSignedOff) {
                throw FeeException::signOffRequired(Money::paise($limit)->format());
            }

            $applied = $method === RefundMethod::Adjustment ? $this->credit($refund) : null;
            $reference = trim((string) $reference);

            $refund->forceFill([
                'status' => RefundStatus::Approved,
                'method' => $method,
                'reference' => $reference === '' ? null : mb_substr($reference, 0, 60),
                'principal_signed_off' => $overLimit,
                'applied_to' => $applied,
                'decided_by_staff_id' => $this->staff->forIdentity($actor)?->staffKey,
                'decided_at' => now(),
            ])->save();

            $this->audit(new AuditEntry('fees.refund_approved', 'fee_refund', $refund->ulid, ['status' => 'pending'], [
                'status' => 'approved',
                'amount_paise' => $refund->amount_paise,
                'method' => $method->value,
                'principal_signed_off' => $overLimit,
                'installments' => $applied === null ? 0 : count($applied),
            ]));

            return $refund;
        });

        $this->notifier->refundDecided($refund->ulid, true);

        return $refund;
    }

    /**
     * @return list<array{invoice: string, amount_paise: int}>
     */
    private function credit(FeeRefund $refund): array
    {
        $invoices = FeeInvoice::query()
            ->where('student_id', $refund->student_id)
            ->orderBy('due_on')->orderBy('id')
            ->lockForUpdate()
            ->get()
            ->filter(fn (FeeInvoice $invoice) => $invoice->balancePaise() > 0)
            ->values();

        $balance = Money::paise((int) $invoices->sum(fn (FeeInvoice $invoice) => $invoice->balancePaise()));

        if ($balance->paise < $refund->amount_paise) {
            throw FeeException::adjustmentShort($balance->format());
        }

        $left = Money::paise($refund->amount_paise);
        $applied = [];

        foreach ($invoices as $invoice) {
            if (! $left->isPositive()) {
                break;
            }

            $take = Money::paise($invoice->balancePaise())->atMost($left);
            $invoice->paid_paise += $take->paise;
            $invoice->save();
            $left = $left->minus($take);
            $applied[] = ['invoice' => $invoice->ulid, 'amount_paise' => $take->paise];
        }

        return $applied;
    }
}
