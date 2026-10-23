<?php

namespace App\Http\Controllers\School;

use App\Domain\Identity\Models\Identity;
use App\Domain\School\Queries\SubjectsOverviewQuery;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class SubjectsScreenController extends Controller
{
    /**
     * The school's subject list, what each class studies and who teaches it,
     * with the sections narrowed to the campus chosen in the top bar.
     */
    public function index(Request $request, SubjectsOverviewQuery $overview): Response
    {
        Gate::authorize('structure.view');

        $identity = $request->user();
        abort_unless($identity instanceof Identity, 403);

        $campus = $request->session()->get('school.campus');
        $section = $request->query('section');

        return Inertia::render('school/subjects', [
            'overview' => $overview->get(
                $identity,
                is_string($campus) && $campus !== '' ? $campus : null,
                is_string($section) && $section !== '' ? $section : null,
            ),
        ]);
    }
}
