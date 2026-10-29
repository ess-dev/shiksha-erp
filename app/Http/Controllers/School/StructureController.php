<?php

namespace App\Http\Controllers\School;

use App\Domain\Access\Queries\ActiveStaffQuery;
use App\Domain\Identity\Models\Identity;
use App\Domain\School\Queries\CampusesQuery;
use App\Domain\School\Queries\SchoolDefinitionsQuery;
use App\Domain\School\Queries\SectionsQuery;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class StructureController extends Controller
{
    /**
     * The school structure: years, campuses, classes, subjects and the sections
     * the signed-in person may see.
     */
    public function index(
        Request $request,
        CampusesQuery $campuses,
        SchoolDefinitionsQuery $definitions,
        SectionsQuery $sections,
        ActiveStaffQuery $staff,
    ): Response {
        Gate::authorize('structure.view');

        $identity = $request->user();
        abort_unless($identity instanceof Identity, 403);

        $filters = $request->validate([
            'year' => ['nullable', 'string', 'max:26'],
            'campus' => ['nullable', 'string', 'max:26'],
            'class' => ['nullable', 'string', 'max:26'],
        ]);

        return Inertia::render('school/structure', [
            'academicYears' => $definitions->academicYears($identity),
            'campuses' => $campuses->get($identity),
            'classes' => $definitions->classes($identity),
            'subjects' => $definitions->subjects($identity),
            'classLadder' => Gate::allows('structure.manage') ? config('shiksha.class_ladder') : [],
            // Only people who can change structure see (and need) the staff list.
            'staffOptions' => Gate::allows('structure.manage') ? $staff->options() : [],
            'sections' => $sections->paginate(
                $identity,
                $filters['year'] ?? $definitions->currentAcademicYear($identity)?->ulid,
                $filters['campus'] ?? null,
                $filters['class'] ?? null,
            )->withQueryString(),
            'filters' => [
                'year' => $filters['year'] ?? null,
                'campus' => $filters['campus'] ?? null,
                'class' => $filters['class'] ?? null,
            ],
        ]);
    }
}
