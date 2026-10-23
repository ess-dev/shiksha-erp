<?php

namespace App\Http\Controllers\Guardian;

use App\Domain\Identity\Authentication\SchoolContext;
use App\Domain\Identity\Models\Identity;
use App\Domain\Students\Queries\ParentHomeQuery;
use App\Domain\Students\Queries\ParentPortalContextQuery;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class HomeController extends Controller
{
    /**
     * The parent home for the selected child (`?child=<ulid>`). Attendance is
     * real; the other cards are marked placeholders in the page.
     */
    public function __invoke(Request $request, ParentPortalContextQuery $portal, ParentHomeQuery $home, SchoolContext $contexts): Response
    {
        $identity = $request->user();
        abort_unless($identity instanceof Identity, 403);

        $context = $portal->resolve(
            $identity,
            $request->query('child') === null ? null : $request->string('child')->toString(),
            $request->session()->get('parent.session'),
            $contexts->canActAsStaff($identity),
        );

        return Inertia::render('parent/home', [
            'shell' => $context->shell,
            'home' => $context->child === null ? null : $home->get($context->child, $context->session),
            'session_name' => $context->session['name'],
            'is_current_session' => $context->session['is_current'],
        ]);
    }
}
