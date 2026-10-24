<?php

namespace App\Http\Controllers\Guardian;

use App\Domain\Identity\Authentication\SchoolContext;
use App\Domain\Identity\Models\Identity;
use App\Domain\Students\Data\ParentPortalContext;
use App\Domain\Students\Exceptions\StudentException;
use App\Domain\Students\Queries\ParentPortalContextQuery;
use App\Domain\Students\Queries\ParentResultsQuery;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

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
    public function index(Request $request, ParentResultsQuery $results): Response
    {
        $context = $this->context($request);

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
        ]);
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
