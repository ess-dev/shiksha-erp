<?php

namespace App\Http\Controllers\Guardian;

use App\Domain\Identity\Authentication\SchoolContext;
use App\Domain\Identity\Models\Identity;
use App\Domain\Students\Queries\ParentPortalContextQuery;
use App\Domain\Students\Queries\ParentSubjectsQuery;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class SubjectsController extends Controller
{
    /**
     * What the selected child studies in the viewed session: books, teachers,
     * chapter progress and teacher notes (`?child=<ulid>`).
     */
    public function __invoke(Request $request, ParentPortalContextQuery $portal, ParentSubjectsQuery $subjects, SchoolContext $contexts): Response
    {
        $identity = $request->user();
        abort_unless($identity instanceof Identity, 403);

        $context = $portal->resolve(
            $identity,
            $request->query('child') === null ? null : $request->string('child')->toString(),
            $request->session()->get('parent.session'),
            $contexts->canActAsStaff($identity),
        );

        return Inertia::render('parent/subjects', [
            'shell' => $context->shell,
            'subjects' => $context->child === null ? [] : $subjects->get($context->child, $context->session),
            'child_first_name' => $context->child?->firstName,
            'section_label' => $context->child?->sectionLabel,
            'session_name' => $context->session['name'],
            'is_current_session' => $context->session['is_current'],
        ]);
    }
}
