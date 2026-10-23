<?php

namespace App\Http\Controllers\Students;

use App\Domain\Identity\Models\Identity;
use App\Domain\Students\Actions\LinkSiblings;
use App\Domain\Students\Actions\UnlinkSiblings;
use App\Domain\Students\Models\Student;
use App\Domain\Students\Queries\SiblingCandidatesQuery;
use App\Http\Controllers\Controller;
use App\Http\Requests\Students\LinkSiblingRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;

class SiblingController extends Controller
{
    /**
     * Students that could be linked as a sibling, found by name or admission number.
     */
    public function candidates(Request $request, Student $student, SiblingCandidatesQuery $candidates): JsonResponse
    {
        Gate::authorize('students.link_siblings');

        $identity = $request->user();
        abort_unless($identity instanceof Identity, 403);

        $term = (string) ($request->validate(['q' => ['nullable', 'string', 'max:100']])['q'] ?? '');

        return response()->json(array_map(
            fn ($candidate) => $candidate->toArray(),
            $candidates->search($identity, $student, $term),
        ));
    }

    public function store(LinkSiblingRequest $request, Student $student, LinkSiblings $linkSiblings): RedirectResponse
    {
        $identity = $request->user();
        abort_unless($identity instanceof Identity, 403);

        $linkSiblings->handle($identity, $student, $request->sibling());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Siblings linked.')]);

        return back();
    }

    public function destroy(Request $request, Student $student, Student $sibling, UnlinkSiblings $unlinkSiblings): RedirectResponse
    {
        Gate::authorize('students.link_siblings');

        $identity = $request->user();
        abort_unless($identity instanceof Identity, 403);

        $unlinkSiblings->handle($identity, $student, $sibling);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Sibling link removed.')]);

        return back();
    }
}
