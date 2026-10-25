<?php

namespace App\Http\Controllers\Fees;

use App\Domain\Fees\Actions\RecordCounterPayment;
use App\Domain\Fees\Enums\PaymentStatus;
use App\Domain\Fees\Queries\CollectFeeQuery;
use App\Domain\Identity\Models\Identity;
use App\Http\Controllers\Controller;
use App\Http\Requests\Fees\CollectFeeRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class CollectController extends Controller
{
    /**
     * Counter fee collection: search for a student (`?q=`), pick one
     * (`?student=<ulid>`) and see what they owe.
     */
    public function index(Request $request, CollectFeeQuery $collect): Response
    {
        Gate::authorize('fees.collect');

        $input = $request->validate([
            'q' => ['nullable', 'string', 'max:100'],
            'student' => ['nullable', 'string', 'max:26'],
        ]);

        $campus = $request->session()->get('school.campus');

        return Inertia::render('fees/collect', [
            'screen' => $collect->screen(
                $this->actor($request),
                (string) ($input['q'] ?? ''),
                ($input['student'] ?? '') !== '' ? $input['student'] : null,
                is_string($campus) && $campus !== '' ? $campus : null,
            ),
        ]);
    }

    /**
     * Records the payment and sends the accountant to the Payments screen,
     * where its receipt can be printed. A cheque lands there as pending.
     */
    public function store(CollectFeeRequest $request, RecordCounterPayment $record): RedirectResponse
    {
        $payment = $record->handle($this->actor($request), $request->payment());

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => $payment->status === PaymentStatus::Success
                ? __('Payment recorded. Receipt :number issued.', ['number' => $payment->receipt_number ?? '—'])
                : __('Cheque recorded. It stays pending until it clears.'),
        ]);

        return to_route('fees.payments.index', ['q' => $payment->receipt_number ?? '']);
    }

    private function actor(Request $request): Identity
    {
        $identity = $request->user();
        abort_unless($identity instanceof Identity, 403);

        return $identity;
    }
}
