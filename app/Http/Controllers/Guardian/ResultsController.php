<?php

namespace App\Http\Controllers\Guardian;

use App\Domain\Exams\Actions\OpenReportCardFile;
use App\Domain\Exams\Enums\ReportCardKind;
use App\Domain\Exams\Models\ReportCard;
use App\Domain\Exams\Queries\StudentReportCardsQuery;
use App\Domain\Identity\Authentication\SchoolContext;
use App\Domain\Identity\Models\Identity;
use App\Domain\Students\Data\ParentPortalContext;
use App\Domain\Students\Data\ReportCardData;
use App\Domain\Students\Exceptions\StudentException;
use App\Domain\Students\Queries\ParentPortalContextQuery;
use App\Domain\Students\Queries\ParentResultsQuery;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ResultsController extends Controller
{
    public function __construct(
        private readonly ParentPortalContextQuery $portal,
        private readonly SchoolContext $contexts,
    ) {}

    /**
     * The selected child's published exam results in the viewed session
     * (`?child=<ulid>&exam=<ulid>`; the exam only picks the first chip shown).
     */
    public function index(Request $request, ParentResultsQuery $results, StudentReportCardsQuery $schoolCards): Response
    {
        $context = $this->context($request);
        $child = $context->child;

        return Inertia::render('parent/results', [
            'shell' => $context->shell,
            'results' => $context->child === null ? null : $results->get($context->child, $context->session),
            'selected_exam' => is_string($request->query('exam')) ? $request->query('exam') : null,
            'child_name' => $context->child?->fullName,
            'child_first_name' => $context->child?->firstName,
            'admission_number' => $context->child === null ? null : $results->admissionNumber($context->child),
            'section_label' => $context->child?->sectionLabel,
            'session_name' => $context->session['name'],
            'is_current_session' => $context->session['is_current'],
            'school_report_cards' => $child === null ? [] : $schoolCards->published($child->studentKey, $context->session['key'], fn (ReportCard $card) => $card->kind === ReportCardKind::File
                ? URL::temporarySignedRoute('parent.report-cards.file', now()->addMinutes(5), ['reportCard' => $card->ulid, 'child' => $child->ulid])
                : route('parent.report-cards.show', ['reportCard' => $card->ulid, 'child' => $child->ulid])),
            'report_card_waiting' => $child !== null && $context->session['is_current'] && $schoolCards->hasWaiting($child->studentKey, $context->session['key']),
        ]);
    }

    /**
     * A report card the class teacher generated, as it was approved.
     */
    public function schoolCard(Request $request, string $reportCard, StudentReportCardsQuery $schoolCards): Response
    {
        $card = $this->publishedCard($request, $reportCard, $schoolCards);

        if ($card->snapshot === null) {
            throw StudentException::notFound();
        }

        return Inertia::render('parent/report-card', [
            'card' => ReportCardData::from($card->snapshot),
            'child' => (string) $this->context($request)->child?->ulid,
        ]);
    }

    /**
     * A report card the class teacher uploaded, from a short-lived signed
     * link. Every opening is audited.
     */
    public function schoolCardFile(Request $request, string $reportCard, StudentReportCardsQuery $schoolCards, OpenReportCardFile $open): StreamedResponse
    {
        $card = $this->publishedCard($request, $reportCard, $schoolCards);
        $path = $open->handle($card);

        return Storage::disk('tenant')->response($path, (string) $card->file_name, [
            'Content-Type' => (string) $card->mime_type,
            'X-Content-Type-Options' => 'nosniff',
            'Cache-Control' => 'private, no-store',
        ]);
    }

    /**
     * A published report card of the selected child (`?child=<ulid>`), or a 404.
     */
    private function publishedCard(Request $request, string $ulid, StudentReportCardsQuery $schoolCards): ReportCard
    {
        $child = $this->context($request)->child ?? throw StudentException::notFound();

        return $schoolCards->findPublished($ulid, $child->studentKey) ?? throw StudentException::notFound();
    }

    /**
     * The printable report card for one exam of the selected child.
     */
    public function reportCard(Request $request, string $exam, ParentResultsQuery $results): Response
    {
        $context = $this->context($request);

        if ($context->child === null) {
            throw StudentException::notFound();
        }

        return Inertia::render('parent/report-card', [
            'card' => $results->reportCard($context->child, $context->session, $exam),
            'child' => $context->child->ulid,
        ]);
    }

    private function context(Request $request): ParentPortalContext
    {
        $identity = $request->user();
        abort_unless($identity instanceof Identity, 403);

        return $this->portal->resolve(
            $identity,
            $request->query('child') === null ? null : $request->string('child')->toString(),
            $request->session()->get('parent.session'),
            $this->contexts->canActAsStaff($identity),
        );
    }
}
