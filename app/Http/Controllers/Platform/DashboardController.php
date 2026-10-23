<?php

namespace App\Http\Controllers\Platform;

use App\Domain\Platform\Queries\PlatformOverviewQuery;
use App\Http\Controllers\Controller;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function index(PlatformOverviewQuery $overview): Response
    {
        return Inertia::render('platform/dashboard', [
            'overview' => $overview->get(),
        ]);
    }
}
