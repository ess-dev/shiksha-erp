<?php

namespace App\Http\Controllers\School;

use App\Domain\Exams\Enums\ReportCardStatus;
use App\Domain\Exams\Queries\ReportCardApprovalsQuery;
use App\Domain\Exams\Queries\ReportCardsScreenQuery;
use App\Domain\Identity\Models\Identity;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class ApprovalsController extends Controller
{
    /**
     * The approvals inbox. Only the Report cards tab has data so far (profile
     * changes, student leave and teacher leave are not built); it needs
     * `reportcards.approve`. `?tab=` picks the tab, `?class=<section ulid>`
     * narrows the report cards, and a partial reload adds `view` (an existing
     * card, `?view=<report card ulid>`).
     */
    public function index(Request $request, ReportCardApprovalsQuery $approvals, ReportCardsScreenQuery $screen): Response
    {
        Gate::authorize('reportcards.approve');

        $actor = $request->user();
        abort_unless($actor instanceof Identity, 403);

        $class = is_string($request->query('class')) && $request->query('class') !== '' ? $request->query('class') : null;
        $view = is_string($request->query('view')) && $request->query('view') !== '' ? $request->query('view') : null;

        return Inertia::render('school/approvals', [
            'report_cards' => $approvals->get($actor, ReportCardStatus::Submitted, $class),
            'view' => Inertia::optional(fn () => $view === null ? null : $screen->view($actor, $view)),
        ]);
    }
}
