<?php

namespace App\Domain\Fees\Actions;

use App\Domain\Audit\Data\AuditEntry;
use App\Domain\Fees\Contracts\FeeNotifier;
use App\Domain\Fees\Enums\PaymentStatus;
use App\Domain\Fees\Exceptions\FeeException;
use App\Domain\Fees\FeeVisibility;
use App\Domain\Fees\Models\FeeInvoice;
use App\Domain\Fees\Models\FeePayment;
use App\Domain\Fees\Models\FeePaymentAllocation;
use App\Domain\Identity\Models\Identity;
use App\Domain\Shared\Action;

class SendPaymentLink extends Action
{
    public function __construct(
        private readonly FeeVisibility $visibility,
        private readonly FeeNotifier $notifier,
    ) {}

    /**
     * Sends the guardian a secure link to pay again what a failed payment was
     * for (through the FeeNotifier; the SMS provider is an open decision).
     * Only for a failed payment whose installments are still unpaid. Needs
     * `fees.remind` over the student's section.
     */
    public function handle(Identity $actor, FeePayment $payment): FeePayment
    {
        $this->visibility->assertAllows($actor, $payment->student_id, 'fees.remind');

        $stillOwed = FeeInvoice::query()
            ->whereIn('id', FeePaymentAllocation::query()->where('fee_payment_id', $payment->id)->pluck('fee_invoice_id'))
            ->get()
            ->contains(fn (FeeInvoice $invoice) => $invoice->balancePaise() > 0);

        if ($payment->status !== PaymentStatus::Failed || ! $stillOwed) {
            throw FeeException::notFailed();
        }

        $this->transaction(function () use ($payment) {
            $payment->forceFill(['link_sent_at' => now()])->save();

            $this->audit(new AuditEntry('fees.payment_link_sent', 'fee_payment', $payment->ulid, null, ['resent' => true]));
        });

        $this->notifier->sendPaymentLink($payment->ulid);

        return $payment;
    }
}
