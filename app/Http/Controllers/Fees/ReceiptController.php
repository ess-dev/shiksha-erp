<?php

namespace App\Http\Controllers\Fees;

use App\Domain\Fees\FeeVisibility;
use App\Domain\Fees\Models\FeePayment;
use App\Domain\Fees\Queries\PaymentViewQuery;
use App\Domain\Identity\Models\Identity;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class ReceiptController extends Controller
{
    /**
     * The printable receipt of a confirmed payment. Out of reach is not found.
     */
    public function show(Request $request, FeePayment $payment, FeeVisibility $visibility, PaymentViewQuery $views): Response
    {
        Gate::authorize('fees.view');

        $identity = $request->user();
        abort_unless($identity instanceof Identity, 403);

        $visible = $visibility->payments($identity, 'fees.view')?->where('fee_payments.id', $payment->id)->exists();
        abort_unless($visible === true, 404);

        $receipt = $views->build(collect([$payment]))[$payment->id]['receipt'] ?? null;
        abort_if($receipt === null, 404);

        return Inertia::render('fees/receipt', [
            'receipt' => $receipt,
            'timezone' => (string) config('shiksha.timezone'),
            'back_url' => route('fees.payments.index'),
        ]);
    }
}
