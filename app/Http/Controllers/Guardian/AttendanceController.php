<?php

namespace App\Http\Controllers\Guardian;

use App\Domain\Identity\Authentication\SchoolContext;
use App\Domain\Identity\Models\Identity;
use App\Domain\Students\Queries\ParentAttendanceQuery;
use App\Domain\Students\Queries\ParentPortalContextQuery;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class AttendanceController extends Controller
{
    /**
     * The monthly attendance calendar of the selected child
     * (`?child=<ulid>&month=YYYY-MM`).
     */
    public function __invoke(Request $request, ParentPortalContextQuery $portal, ParentAttendanceQuery $attendance, SchoolContext $contexts): Response
    {
        $identity = $request->user();
        abort_unless($identity instanceof Identity, 403);

        $context = $portal->resolve(
            $identity,
            $request->query('child') === null ? null : $request->string('child')->toString(),
            $request->session()->get('parent.session'),
            $contexts->canActAsStaff($identity),
        );

        return Inertia::render('parent/attendance', [
            'shell' => $context->shell,
            'attendance' => $context->child === null
                ? null
                : $attendance->get($context->child, $context->session, $request->query('month') === null ? null : $request->string('month')->toString()),
            'class_teacher_name' => $context->child?->classTeacherName,
            'child_first_name' => $context->child?->firstName,
            'session_name' => $context->session['name'],
        ]);
    }
}
