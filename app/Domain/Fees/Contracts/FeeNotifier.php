<?php

namespace App\Domain\Fees\Contracts;

/**
 * Tells guardians about fees. SMS, push and WhatsApp providers are open
 * decisions, so Fees only talks to this interface; the bound driver is the
 * log driver until Communication exists. Implementations get ulids only and
 * work out the guardians themselves.
 */
interface FeeNotifier
{
    /**
     * @param  list<string>  $invoiceUlids  the unpaid installments the reminder is about
     */
    public function remind(array $invoiceUlids): void;

    /**
     * Sends the guardian a secure link to pay what a failed payment was for.
     */
    public function sendPaymentLink(string $paymentUlid): void;
}
