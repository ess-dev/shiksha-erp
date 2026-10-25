<?php

namespace App\Domain\Fees\Actions;

use App\Domain\Audit\Data\AuditEntry;
use App\Domain\Fees\Enums\GatewayReport;
use App\Domain\Fees\Enums\PaymentChannel;
use App\Domain\Fees\Enums\PaymentStatus;
use App\Domain\Fees\Exceptions\FeeException;
use App\Domain\Fees\FeeVisibility;
use App\Domain\Fees\Gateways\PaymentGatewayManager;
use App\Domain\Fees\Models\FeePayment;
use App\Domain\Fees\PaymentSettlement;
use App\Domain\Identity\Models\Identity;
use App\Domain\School\Queries\AcademicSessionsQuery;
use App\Domain\Shared\Action;

class CheckGatewayPayment extends Action
{
    public function __construct(
        private readonly FeeVisibility $visibility,
        private readonly PaymentGatewayManager $gateways,
        private readonly PaymentSettlement $settlement,
        private readonly AcademicSessionsQuery $years,
    ) {}

    /**
     * Asks the school's payment gateway for the final status of an online
     * payment that is still pending (or, with `$recheck`, of one that failed:
     * a bank sometimes takes the money although the app showed a failure). A
     * captured payment is applied to its installments and gets a receipt; a
     * failed one is marked failed; "pending" leaves it as it was. Needs
     * `fees.reconcile` over the student's section.
     *
     * @param  GatewayReport|null  $simulated  what a test gateway should report; real gateways ignore it
     */
    public function handle(Identity $actor, FeePayment $payment, bool $recheck = false, ?GatewayReport $simulated = null): GatewayReport
    {
        $this->visibility->assertAllows($actor, $payment->student_id, 'fees.reconcile');

        if ($payment->channel !== PaymentChannel::Online) {
            throw FeeException::notOnline();
        }

        $expected = $recheck ? PaymentStatus::Failed : PaymentStatus::Pending;

        if ($payment->status !== $expected) {
            throw $recheck ? FeeException::notFailed() : FeeException::notPending();
        }

        $gateway = $this->gateways->current() ?? throw FeeException::noGateway();
        $report = $gateway->status((string) $payment->reference, $gateway->isSimulated() ? $simulated : null);
        $year = $this->years->find() ?? throw FeeException::noAcademicYear();

        return $this->transaction(function () use ($payment, $recheck, $report, $year) {
            $payment = FeePayment::query()->whereKey($payment->id)->lockForUpdate()->firstOrFail();
            $expected = $recheck ? PaymentStatus::Failed : PaymentStatus::Pending;

            if ($payment->status !== $expected) {
                throw FeeException::notPending();
            }

            $before = $payment->status->value;

            if ($report === GatewayReport::Captured) {
                $this->settlement->confirm($payment, $year['starts_on'], $recheck);
            } elseif ($report === GatewayReport::Failed && ! $recheck) {
                $payment->forceFill([
                    'status' => PaymentStatus::Failed,
                    'failure_reason' => 'The gateway reported the payment as failed',
                    'settled_at' => now(),
                ])->save();
            }

            $this->audit(new AuditEntry($recheck ? 'fees.payment_rechecked' : 'fees.payment_checked', 'fee_payment', $payment->ulid, ['status' => $before], [
                'status' => $payment->status->value,
                'gateway_report' => $report->value,
            ]));

            return $report;
        });
    }
}
