<?php

namespace App\Http\Controllers\Fees;

use App\Domain\Fees\Actions\ExportDues;
use App\Domain\Fees\Actions\SendFeeReminders;
use App\Domain\Fees\DueFilters;
use App\Domain\Fees\Queries\DuesQuery;
use App\Domain\Identity\Models\Identity;
use App\Domain\Shared\CalendarDate;
use App\Domain\Shared\CsvCell;
use App\Http\Controllers\Controller;
use App\Http\Requests\Fees\SendRemindersRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use SplFileObject;
use Symfony\Component\HttpFoundation\StreamedResponse;

class DuesController extends Controller
{
    /**
     * Unpaid installments of the current academic year, filtered and paged,
     * limited to what the person's `fees.view` scope (and the campus chosen in
     * the top bar) covers.
     */
    public function index(Request $request, DuesQuery $dues): Response
    {
        Gate::authorize('fees.view');

        $filters = $this->filters($request);

        return Inertia::render('fees/dues', [
            'screen' => $dues->screen($this->actor($request), $filters, (int) $request->integer('page', 1)),
            'filters' => [
                'q' => $filters->search,
                'class' => $filters->sectionUlid,
                'installment' => $filters->installmentUlid,
                'status' => $filters->status,
                'sort' => $filters->sort,
            ],
        ]);
    }

    /**
     * Downloads the dues the filters match as CSV (audited).
     */
    public function export(Request $request, ExportDues $export): StreamedResponse
    {
        Gate::authorize('fees.view');

        $rows = $export->handle($this->actor($request), $this->filters($request));

        return response()->streamDownload(function () use ($rows) {
            $out = new SplFileObject('php://output', 'w');
            $out->fwrite("\xEF\xBB\xBF");

            foreach ($rows as $row) {
                $out->fputcsv(array_map(CsvCell::safe(...), $row), ',', '"', '');
            }
        }, 'fee-dues-'.CalendarDate::today().'.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    /**
     * Reminds the guardians of the ticked installments.
     */
    public function remind(SendRemindersRequest $request, SendFeeReminders $reminders): RedirectResponse
    {
        $result = $reminders->handle($this->actor($request), $request->invoiceUlids());

        $families = $result['students'] === 1 ? '1 family' : $result['students'].' families';

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Reminders recorded for :families (no SMS provider is set up yet, so nothing was sent).', ['families' => $families])]);

        return back();
    }

    private function filters(Request $request): DueFilters
    {
        $input = $request->validate([
            'q' => ['nullable', 'string', 'max:100'],
            'class' => ['nullable', 'string', 'max:26'],
            'installment' => ['nullable', 'string', 'max:26'],
            'status' => ['nullable', Rule::in(['open', 'overdue', 'due', 'partial', 'upcoming'])],
            'sort' => ['nullable', Rule::in(['due', 'balance', 'class'])],
            'page' => ['nullable', 'integer', 'min:1', 'max:100000'],
        ]);

        $campus = $request->session()->get('school.campus');

        return new DueFilters(
            search: (string) ($input['q'] ?? ''),
            sectionUlid: ($input['class'] ?? '') !== '' ? $input['class'] : null,
            installmentUlid: ($input['installment'] ?? '') !== '' ? $input['installment'] : null,
            status: $input['status'] ?? 'open',
            sort: $input['sort'] ?? 'due',
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
