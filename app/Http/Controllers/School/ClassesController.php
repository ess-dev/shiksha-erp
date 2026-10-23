<?php

namespace App\Http\Controllers\School;

use App\Domain\Identity\Models\Identity;
use App\Domain\School\Queries\ClassesOverviewQuery;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class ClassesController extends Controller
{
    /**
     * Classes and sections of the current academic year, narrowed to the
     * campus chosen in the top bar.
     */
    public function index(Request $request, ClassesOverviewQuery $overview): Response
    {
        Gate::authorize('structure.view');

        $identity = $request->user();
        abort_unless($identity instanceof Identity, 403);

        $campus = $request->session()->get('school.campus');

        return Inertia::render('school/classes', [
            'overview' => $overview->get($identity, is_string($campus) && $campus !== '' ? $campus : null),
        ]);
    }
}
