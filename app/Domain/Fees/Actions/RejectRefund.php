<?php

namespace App\Domain\Fees\Actions;

use App\Domain\Access\Queries\StaffRefQuery;
use App\Domain\Audit\Data\AuditEntry;
use App\Domain\Fees\Contracts\FeeNotifier;
use App\Domain\Fees\Enums\RefundStatus;
use App\Domain\Fees\Exceptions\FeeException;
use App\Domain\Fees\FeeVisibility;
use App\Domain\Fees\Models\FeeRefund;
use App\Domain\Identity\Models\Identity;
use App\Domain\Shared\Action;

class RejectRefund extends Action
{
    public function __construct(
        private readonly FeeVisibility $visibility,
        private readonly StaffRefQuery $staff,
        private readonly FeeNotifier $notifier,
    ) {}

    /**
     * Turns a pending refund down. The reason is shared with the parent.
     * Needs `fees.refund` over the student.
     */
    public function handle(Identity $actor, FeeRefund $refund, string $reason): FeeRefund
    {
        $this->visibility->assertAllows($actor, $refund->student_id, 'fees.refund');

        $reason = trim($reason);

        if (mb_strlen($reason) < 5) {
            throw FeeException::detailRequired('a short reason');
        }

        $refund = $this->transaction(function () use ($actor, $refund, $reason) {
            $refund = FeeRefund::query()->whereKey($refund->id)->lockForUpdate()->firstOrFail();

            if ($refund->status !== RefundStatus::Pending) {
                throw FeeException::refundNotPending();
            }

            $refund->forceFill([
                'status' => RefundStatus::Rejected,
                'reject_reason' => mb_substr($reason, 0, 300),
                'decided_by_staff_id' => $this->staff->forIdentity($actor)?->staffKey,
                'decided_at' => now(),
            ])->save();

            $this->audit(new AuditEntry('fees.refund_rejected', 'fee_refund', $refund->ulid, ['status' => 'pending'], [
                'status' => 'rejected',
                'amount_paise' => $refund->amount_paise,
            ]));

            return $refund;
        });

        $this->notifier->refundDecided($refund->ulid, false);

        return $refund;
    }
}
