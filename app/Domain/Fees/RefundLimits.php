<?php

namespace App\Domain\Fees;

use App\Domain\Fees\Enums\PaymentStatus;
use App\Domain\Fees\Enums\RefundStatus;
use App\Domain\Fees\Models\FeePayment;
use App\Domain\Fees\Models\FeeRefund;
use Illuminate\Database\Eloquent\Builder;

/**
 * How much can still be refunded: money actually received, less refunds that
 * are pending or approved (a rejected request frees its amount again).
 */
class RefundLimits
{
    public function forPayment(FeePayment $payment): int
    {
        if ($payment->status !== PaymentStatus::Success) {
            return 0;
        }

        return max(0, $payment->amount_paise - $this->taken(FeeRefund::query()->where('fee_payment_id', $payment->id)));
    }

    public function forStudent(int $studentId): int
    {
        $received = (int) FeePayment::query()
            ->where('student_id', $studentId)->where('status', PaymentStatus::Success->value)->sum('amount_paise');

        return max(0, $received - $this->taken(FeeRefund::query()->where('student_id', $studentId)));
    }

    /**
     * @param  Builder<FeeRefund>  $refunds
     */
    private function taken(Builder $refunds): int
    {
        return (int) $refunds->where('status', '!=', RefundStatus::Rejected->value)->sum('amount_paise');
    }
}
