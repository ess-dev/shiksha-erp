<?php

namespace App\Domain\Fees\Actions;

use App\Domain\Audit\Data\AuditEntry;
use App\Domain\Fees\Enums\PaymentMode;
use App\Domain\Fees\Enums\PaymentStatus;
use App\Domain\Fees\Exceptions\FeeException;
use App\Domain\Fees\FeeVisibility;
use App\Domain\Fees\Models\FeePayment;
use App\Domain\Fees\PaymentSettlement;
use App\Domain\Identity\Models\Identity;
use App\Domain\School\Queries\AcademicSessionsQuery;
use App\Domain\Shared\Action;

class SettleChequePayment extends Action
{
    /** illustrative — verify: the prototype's choices of the bank's reason for a bounced cheque. */
    public const BOUNCE_REASONS = ['Insufficient funds', 'Signature mismatch', 'Stale or post-dated cheque', 'Account closed', 'Other'];

    public function __construct(
        private readonly FeeVisibility $visibility,
        private readonly PaymentSettlement $settlement,
        private readonly AcademicSessionsQuery $years,
    ) {}

    /**
     * A cheque has cleared (the payment is confirmed and a receipt issued) or
     * bounced (the payment fails and the installments stay unpaid). Needs
     * `fees.reconcile` over the student's section.
     *
     * @param  string|null  $bounceReason  one of BOUNCE_REASONS, only when bounced
     */
    public function handle(Identity $actor, FeePayment $payment, bool $cleared, ?string $bounceReason = null): FeePayment
    {
        $this->visibility->assertAllows($actor, $payment->student_id, 'fees.reconcile');

        if ($payment->status !== PaymentStatus::Pending || $payment->mode !== PaymentMode::Cheque) {
            throw $payment->mode === PaymentMode::Cheque ? FeeException::notPending() : FeeException::notCheque();
        }

        if (! $cleared && ! in_array($bounceReason, self::BOUNCE_REASONS, true)) {
            throw FeeException::amountInvalid('Choose the bank’s reason.');
        }

        $year = $this->years->find() ?? throw FeeException::noAcademicYear();

        return $this->transaction(function () use ($payment, $cleared, $bounceReason, $year) {
            $payment = FeePayment::query()->whereKey($payment->id)->lockForUpdate()->firstOrFail();

            if ($payment->status !== PaymentStatus::Pending) {
                throw FeeException::notPending();
            }

            if ($cleared) {
                $result = $this->settlement->confirm($payment, $year['starts_on']);
            } else {
                $payment->forceFill([
                    'status' => PaymentStatus::Failed,
                    'failure_reason' => 'Cheque bounced: '.mb_strtolower((string) $bounceReason),
                    'settled_at' => now(),
                ])->save();
                $result = null;
            }

            $this->audit(new AuditEntry('fees.cheque_settled', 'fee_payment', $payment->ulid, ['status' => 'pending'], [
                'status' => $payment->status->value,
                'applied_paise' => $result['applied_paise'] ?? 0,
                'excess_paise' => $result['excess_paise'] ?? 0,
                'reason' => $cleared ? null : $bounceReason,
            ]));

            return $payment;
        });
    }
}
