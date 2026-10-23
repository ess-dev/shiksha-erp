<?php

namespace App\Http\Controllers\School;

use App\Domain\Identity\Models\Identity;
use App\Domain\School\Queries\SchoolDashboardQuery;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    /**
     * The staff dashboard. Any signed-in staff member may open it (the
     * membership middleware has already checked them); each block is limited
     * by the person's own permissions and scope inside the queries.
     */
    public function __invoke(Request $request, SchoolDashboardQuery $dashboard): Response
    {
        $identity = $request->user();
        abort_unless($identity instanceof Identity, 403);

        $campus = $request->session()->get('school.campus');

        return Inertia::render('dashboard', [
            'dashboard' => $dashboard->get($identity, is_string($campus) && $campus !== '' ? $campus : null),
        ]);
    }
}
