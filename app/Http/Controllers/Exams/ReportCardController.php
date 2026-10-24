<?php

namespace App\Http\Controllers\Exams;

use App\Domain\Exams\Actions\DecideReportCards;
use App\Domain\Exams\Actions\GenerateReportCards;
use App\Domain\Exams\Actions\OpenReportCardFile;
use App\Domain\Exams\Actions\UploadReportCard;
use App\Domain\Exams\Actions\WithdrawReportCard;
use App\Domain\Exams\Enums\ReportCardStatus;
use App\Domain\Exams\Exceptions\ReportCardException;
use App\Domain\Exams\Models\Exam;
use App\Domain\Exams\Models\ReportCard;
use App\Domain\Exams\Queries\ReportCardApprovalsQuery;
use App\Domain\Exams\Queries\ReportCardsScreenQuery;
use App\Domain\Exams\ReportCardAccess;
use App\Domain\Identity\Models\Identity;
use App\Domain\School\Queries\SectionDirectoryQuery;
use App\Http\Controllers\Controller;
use App\Http\Requests\Exams\DecideReportCardsRequest;
use App\Http\Requests\Exams\GenerateReportCardsRequest;
use App\Http\Requests\Exams\UploadReportCardRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ReportCardController extends Controller
{
    /**
     * The class teacher's Report cards screen (`?section=<ulid>&exam=<ulid>`).
     * Approvers also get `approvals` (`?status=submitted|published|returned|all`,
     * `?class=<section ulid>`). Partial reloads add `preview` (a new card for `?preview=<student ulid>`)
     * or `view` (an existing card, `?view=<report card ulid>`).
     */
    public function index(Request $request, ReportCardsScreenQuery $screen, ReportCardApprovalsQuery $approvals): Response
    {
        $mayApprove = Gate::allows('reportcards.approve');
        abort_unless($mayApprove || Gate::allows('reportcards.prepare'), 403);

        $actor = $this->actor($request);
        $query = fn (string $key): ?string => is_string($request->query($key)) && $request->query($key) !== '' ? $request->query($key) : null;
        $data = $screen->get($actor, $query('section'), $query('exam'));
        $status = $query('status') === 'all' ? null : (ReportCardStatus::tryFrom((string) $query('status')) ?? ReportCardStatus::Submitted);

        return Inertia::render('exams/report-cards', [
            'screen' => $data,
            'approvals' => $mayApprove ? $approvals->get($actor, $status, $query('class')) : null,
            'preview' => Inertia::optional(fn () => $query('preview') === null || $data->section_ulid === null || $data->exam_ulid === null
                ? null
                : $screen->preview($actor, $data->section_ulid, $data->exam_ulid, (string) $query('preview'))),
            'view' => Inertia::optional(fn () => $query('view') === null ? null : $screen->view($actor, (string) $query('view'))),
        ]);
    }

    public function generate(GenerateReportCardsRequest $request, Exam $exam, GenerateReportCards $generate): RedirectResponse
    {
        $count = $generate->handle($this->actor($request), $exam, $request->string('section')->toString(), $request->remarks());

        Inertia::flash('toast', ['type' => 'success', 'message' => trans_choice('{1} 1 report card ready.|[2,*] :count report cards ready.', $count)]);

        return back();
    }

    public function upload(UploadReportCardRequest $request, Exam $exam, UploadReportCard $upload): RedirectResponse
    {
        $upload->handle($this->actor($request), $exam, $request->string('section')->toString(), $request->string('student')->toString(), $request->file('file'));

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Report card uploaded.')]);

        return back();
    }

    public function withdraw(Request $request, ReportCard $reportCard, WithdrawReportCard $withdraw): RedirectResponse
    {
        Gate::authorize('reportcards.prepare');

        $withdraw->handle($this->actor($request), $reportCard);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Withdrawn. You can change it and send again.')]);

        return back();
    }

    public function decide(DecideReportCardsRequest $request, Exam $exam, DecideReportCards $decide): RedirectResponse
    {
        $approve = $request->boolean('approve');
        $count = $decide->handle(
            $this->actor($request),
            $exam,
            $request->string('section')->toString(),
            $approve,
            $request->filled('note') ? $request->string('note')->toString() : null,
            $request->students(),
        );

        Inertia::flash('toast', ['type' => 'success', 'message' => $approve
            ? trans_choice('{1} 1 report card published.|[2,*] :count report cards published.', $count)
            : trans_choice('{1} 1 report card sent back.|[2,*] :count report cards sent back.', $count)]);

        return back();
    }

    /**
     * Streams an uploaded report card to its class teacher or an approver,
     * from a short-lived signed link. Every opening is audited.
     */
    public function file(Request $request, ReportCard $reportCard, ReportCardAccess $access, SectionDirectoryQuery $sections, OpenReportCardFile $open): StreamedResponse
    {
        $actor = $this->actor($request);
        $section = $sections->byKeys([$reportCard->section_id])[$reportCard->section_id] ?? throw ReportCardException::notFound();
        $isPreparer = array_filter($access->preparerSections($actor), fn ($s) => $s->sectionKey === $reportCard->section_id) !== [];

        if (! $isPreparer && ! $access->mayApprove($actor, $section)) {
            throw ReportCardException::notFound();
        }

        $path = $open->handle($reportCard);

        return Storage::disk('tenant')->response($path, (string) $reportCard->file_name, [
            'Content-Type' => (string) $reportCard->mime_type,
            'X-Content-Type-Options' => 'nosniff',
            'Cache-Control' => 'private, no-store',
        ]);
    }

    private function actor(Request $request): Identity
    {
        $identity = $request->user();
        abort_unless($identity instanceof Identity, 403);

        return $identity;
    }
}
