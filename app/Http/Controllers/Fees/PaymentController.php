<?php

namespace App\Http\Controllers\Fees;

use App\Domain\Fees\Actions\CheckGatewayPayment;
use App\Domain\Fees\Actions\ExportPayments;
use App\Domain\Fees\Actions\SendPaymentLink;
use App\Domain\Fees\Actions\SettleChequePayment;
use App\Domain\Fees\Enums\GatewayReport;
use App\Domain\Fees\Models\FeePayment;
use App\Domain\Fees\PaymentFilters;
use App\Domain\Fees\Queries\PaymentsQuery;
use App\Domain\Identity\Models\Identity;
use App\Domain\Shared\CalendarDate;
use App\Domain\Shared\CsvCell;
use App\Http\Controllers\Controller;
use App\Http\Requests\Fees\CheckPaymentRequest;
use App\Http\Requests\Fees\SettleChequeRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use SplFileObject;
use Symfony\Component\HttpFoundation\StreamedResponse;

class PaymentController extends Controller
{
    /**
     * Payments and reconciliation: what needs action first, then newest.
     */
    public function index(Request $request, PaymentsQuery $payments): Response
    {
        Gate::authorize('fees.view');

        $filters = $this->filters($request);

        return Inertia::render('fees/payments', [
            'screen' => $payments->screen($this->actor($request), $filters, (int) $request->integer('page', 1)),
            'timezone' => (string) config('shiksha.timezone'),
            'filters' => [
                'q' => $filters->search,
                'status' => $filters->status,
                'mode' => $filters->mode,
                'from' => $filters->from,
                'to' => $filters->to,
            ],
        ]);
    }

    public function export(Request $request, ExportPayments $export): StreamedResponse
    {
        Gate::authorize('fees.view');

        $rows = $export->handle($this->actor($request), $this->filters($request));

        return response()->streamDownload(function () use ($rows) {
            $out = new SplFileObject('php://output', 'w');
            $out->fwrite("\xEF\xBB\xBF");

            foreach ($rows as $row) {
                $out->fputcsv(array_map(CsvCell::safe(...), $row), ',', '"', '');
            }
        }, 'payments-'.CalendarDate::today().'.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    /**
     * Asks the gateway about a pending online payment.
     */
    public function check(CheckPaymentRequest $request, FeePayment $payment, CheckGatewayPayment $check): RedirectResponse
    {
        $report = $check->handle($this->actor($request), $payment, false, $request->simulatedReport());

        Inertia::flash('toast', match ($report) {
            GatewayReport::Captured => ['type' => 'success', 'message' => __('Payment confirmed. A receipt was issued.')],
            GatewayReport::Failed => ['type' => 'info', 'message' => __('Marked as failed. The family can pay again.')],
            GatewayReport::Pending => ['type' => 'info', 'message' => __('The gateway has not confirmed it yet. Try again later.')],
        });

        return back();
    }

    /**
     * Asks the gateway again about a payment that showed as failed.
     */
    public function recheck(CheckPaymentRequest $request, FeePayment $payment, CheckGatewayPayment $check): RedirectResponse
    {
        $report = $check->handle($this->actor($request), $payment, true, $request->simulatedReport());

        Inertia::flash('toast', $report === GatewayReport::Captured
            ? ['type' => 'success', 'message' => __('Payment recovered. A receipt was issued.')]
            : ['type' => 'info', 'message' => __('Still failed. You can send the family a payment link.')]);

        return back();
    }

    /**
     * A cheque cleared, or bounced with the bank's reason.
     */
    public function cheque(SettleChequeRequest $request, FeePayment $payment, SettleChequePayment $settle): RedirectResponse
    {
        $settled = $settle->handle($this->actor($request), $payment, $request->boolean('cleared'), $request->input('reason'));

        Inertia::flash('toast', $request->boolean('cleared')
            ? ['type' => 'success', 'message' => __('Cheque cleared. Receipt :number issued.', ['number' => $settled->receipt_number ?? '—'])]
            : ['type' => 'info', 'message' => __('Cheque marked as bounced.')]);

        return back();
    }

    /**
     * Sends the guardian a link to pay again.
     */
    public function link(Request $request, FeePayment $payment, SendPaymentLink $send): RedirectResponse
    {
        Gate::authorize('fees.remind');

        $send->handle($this->actor($request), $payment);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Payment link recorded (no SMS provider is set up yet, so nothing was sent).')]);

        return back();
    }

    private function filters(Request $request): PaymentFilters
    {
        $input = $request->validate([
            'q' => ['nullable', 'string', 'max:100'],
            'status' => ['nullable', Rule::in(['all', 'pending', 'failed', 'success'])],
            'mode' => ['nullable', 'string', 'max:20'],
            'from' => ['nullable', 'date_format:Y-m-d'],
            'to' => ['nullable', 'date_format:Y-m-d'],
            'page' => ['nullable', 'integer', 'min:1', 'max:100000'],
        ]);

        $campus = $request->session()->get('school.campus');

        return new PaymentFilters(
            search: (string) ($input['q'] ?? ''),
            status: $input['status'] ?? 'all',
            mode: $input['mode'] ?? 'all',
            from: ($input['from'] ?? '') !== '' ? $input['from'] : null,
            to: ($input['to'] ?? '') !== '' ? $input['to'] : null,
            campusUlid: is_string($campus) && $campus !== '' ? $campus : null,
        );
    }

    private function actor(Request $request): Identity
    {
        $identity = $request->user();
        abort_unless($identity instanceof Identity, 403);

        return $identity;
    }
}
