<?php

namespace App\Domain\Fees;

use App\Domain\Fees\Contracts\FeeNotifier;
use Illuminate\Support\Facades\Log;

/**
 * Stand-in notifier: records that guardians would be told, never who. Replace
 * the binding when Communication and an SMS/push provider exist.
 */
class LogFeeNotifier implements FeeNotifier
{
    public function remind(array $invoiceUlids): void
    {
        Log::info('Fee reminder would be sent (no provider configured).', ['invoices' => count($invoiceUlids)]);
    }

    public function sendPaymentLink(string $paymentUlid): void
    {
        Log::info('Payment link would be sent (no provider configured).', ['payment' => $paymentUlid]);
    }
}
