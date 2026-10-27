<?php

namespace App\Http\Controllers\Fees;

use App\Domain\Fees\Actions\ApproveRefund;
use App\Domain\Fees\Actions\RejectRefund;
use App\Domain\Fees\Actions\RequestRefund;
use App\Domain\Fees\Enums\RefundMethod;
use App\Domain\Fees\Models\FeeRefund;
use App\Domain\Fees\Queries\RefundsQuery;
use App\Domain\Identity\Models\Identity;
use App\Domain\Shared\Money;
use App\Http\Controllers\Controller;
use App\Http\Requests\Fees\ApproveRefundRequest;
use App\Http\Requests\Fees\RejectRefundRequest;
use App\Http\Requests\Fees\RequestRefundRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class RefundController extends Controller
{
    /**
     * Refund requests to review: `?tab=` pending, approved, rejected or all,
     * `?q=` finds a student for "New refund request".
     */
    public function index(Request $request, RefundsQuery $refunds): Response
    {
        Gate::authorize('fees.refund');

        $input = $request->validate([
            'tab' => ['nullable', 'string', 'max:10'],
            'q' => ['nullable', 'string', 'max:100'],
        ]);

        $campus = $request->session()->get('school.campus');

        return Inertia::render('fees/refunds', [
            'screen' => $refunds->screen(
                $this->actor($request),
                (string) ($input['tab'] ?? 'pending'),
                (string) ($input['q'] ?? ''),
                is_string($campus) && $campus !== '' ? $campus : null,
            ),
        ]);
    }

    public function store(RequestRefundRequest $request, RequestRefund $requestRefund): RedirectResponse
    {
        $requestRefund->handle(
            $this->actor($request),
            $request->string('student')->toString(),
            Money::fromRupees($request->integer('amount'))->paise,
            $request->string('reason')->toString(),
            $request->filled('payment') ? $request->string('payment')->toString() : null,
        );

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Refund request added.')]);

        return to_route('fees.refunds.index');
    }

    public function approve(ApproveRefundRequest $request, FeeRefund $refund, ApproveRefund $approve): RedirectResponse
    {
        $approve->handle(
            $this->actor($request),
            $refund,
            RefundMethod::from($request->string('method')->toString()),
            $request->input('reference'),
            $request->boolean('principal_signed_off'),
        );

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Refund approved; the parent is notified.')]);

        return back();
    }

    public function reject(RejectRefundRequest $request, FeeRefund $refund, RejectRefund $reject): RedirectResponse
    {
        $reject->handle($this->actor($request), $refund, $request->string('reason')->toString());

        Inertia::flash('toast', ['type' => 'info', 'message' => __('Refund rejected.')]);

        return back();
    }

    private function actor(Request $request): Identity
    {
        $identity = $request->user();
        abort_unless($identity instanceof Identity, 403);

        return $identity;
    }
}
