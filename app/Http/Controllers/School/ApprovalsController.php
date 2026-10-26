<?php

namespace App\Http\Controllers\School;

use App\Domain\Access\Queries\PermissionScopesQuery;
use App\Domain\Exams\Enums\ReportCardStatus;
use App\Domain\Exams\Queries\ReportCardApprovalsQuery;
use App\Domain\Exams\Queries\ReportCardsScreenQuery;
use App\Domain\Identity\Models\Identity;
use App\Domain\Leave\Queries\StaffLeaveQuery;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ApprovalsController extends Controller
{
    /**
     * The approvals inbox. Report cards need `reportcards.approve`; Student
     * leave (long leave a class teacher recommended) needs
     * `leave.approve_long`. Profile changes and teacher leave are not built.
     * Either permission opens the screen; a tab without its permission gets
     * `null`. `?tab=` picks the tab, `?class=<section ulid>` narrows the report
     * cards, and a partial reload adds `view` (an existing card,
     * `?view=<report card ulid>`).
     */
    public function index(Request $request, PermissionScopesQuery $scopes, ReportCardApprovalsQuery $approvals, ReportCardsScreenQuery $screen, StaffLeaveQuery $leave): Response
    {
        $actor = $request->user();
        abort_unless($actor instanceof Identity, 403);

        $canReportCards = $scopes->get($actor, 'reportcards.approve')->allows();
        $canLeave = $scopes->get($actor, 'leave.approve_long')->allows();
        abort_unless($canReportCards || $canLeave, 403);

        $class = is_string($request->query('class')) && $request->query('class') !== '' ? $request->query('class') : null;
        $view = is_string($request->query('view')) && $request->query('view') !== '' ? $request->query('view') : null;

        return Inertia::render('school/approvals', [
            'report_cards' => $canReportCards ? $approvals->get($actor, ReportCardStatus::Submitted, $class) : null,
            'student_leave' => $canLeave ? $leave->approvals($actor) : null,
            'view' => Inertia::optional(fn () => $view === null || ! $canReportCards ? null : $screen->view($actor, $view)),
        ]);
    }
}
