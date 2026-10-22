<?php

namespace App\Http\Controllers\Students;

use App\Domain\Identity\Models\Identity;
use App\Domain\Shared\CalendarDate;
use App\Domain\Students\Actions\UpdateStudentDetails;
use App\Domain\Students\Data\StudentFilters;
use App\Domain\Students\Models\Student;
use App\Domain\Students\Queries\StudentOptionsQuery;
use App\Domain\Students\Queries\StudentProfileQuery;
use App\Domain\Students\Queries\StudentsQuery;
use App\Domain\Students\StudentSession;
use App\Http\Controllers\Controller;
use App\Http\Requests\Students\UpdateStudentRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class StudentController extends Controller
{
    /**
     * The Students list: search, class and status filters, 25 per page, scoped
     * to what the signed-in person may see (and to the campus chosen in the
     * top bar, when they chose one).
     */
    public function index(Request $request, StudentsQuery $students, StudentOptionsQuery $options, StudentSession $sessions): Response
    {
        Gate::authorize('students.view');

        $identity = $request->user();
        abort_unless($identity instanceof Identity, 403);

        $input = $request->validate([
            'q' => ['nullable', 'string', 'max:100'],
            'class' => ['nullable', 'string', 'max:26'],
            'status' => ['nullable', Rule::in(['current', 'left', 'all'])],
            'page' => ['nullable', 'integer', 'min:1', 'max:100000'],
        ]);

        $campus = $request->session()->get('school.campus');
        $campus = is_string($campus) && $campus !== '' ? $campus : null;

        $session = $this->pastSession($request, $sessions, $identity);

        $filters = new StudentFilters(
            search: (string) ($input['q'] ?? ''),
            sectionUlid: ($input['class'] ?? '') !== '' ? $input['class'] : null,
            status: $input['status'] ?? 'current',
            campusUlid: $campus,
            yearId: $session['key'] ?? null,
        );

        return Inertia::render('students/index', [
            'students' => $students->paginate($identity, $filters, (int) ($input['page'] ?? 1)),
            'filters' => ['q' => $filters->search, 'class' => $filters->sectionUlid, 'status' => $filters->status],
            'classes' => $options->classFilter($identity, $campus, $session['key'] ?? null),
            'session' => $session === null ? null : ['name' => $session['name']],
        ]);
    }

    /**
     * One student's profile, tabbed. Out-of-reach students are a 404.
     */
    public function show(Request $request, Student $student, StudentProfileQuery $profile, StudentOptionsQuery $options, StudentSession $sessions): Response
    {
        Gate::authorize('students.view');

        $identity = $request->user();
        abort_unless($identity instanceof Identity, 403);

        $session = $this->pastSession($request, $sessions, $identity);

        return Inertia::render('students/show', [
            'student' => $profile->get($identity, $student, $session['key'] ?? null, $session['name'] ?? null),
            'options' => $options->get(),
            'today' => CalendarDate::today(),
            'tab' => in_array($request->query('tab'), ['overview', 'guardians', 'history', 'attendance', 'fees', 'marks', 'documents'], true) ? $request->query('tab') : 'overview',
        ]);
    }

    /**
     * The past academic session chosen in the top bar, or null for today's
     * students (the current session, or a choice the person may not make).
     *
     * @return array{key: int, name: string}|null
     */
    private function pastSession(Request $request, StudentSession $sessions, Identity $identity): ?array
    {
        $chosen = $request->session()->get('school.session');
        $session = $sessions->resolve($identity, is_string($chosen) ? $chosen : null);

        return $session === null || $session['is_current'] ? null : ['key' => $session['key'], 'name' => $session['name']];
    }

    public function update(UpdateStudentRequest $request, Student $student, UpdateStudentDetails $updateStudent): RedirectResponse
    {
        $identity = $request->user();
        abort_unless($identity instanceof Identity, 403);

        $updateStudent->handle($identity, $student, $request->toUpdate());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Student details saved.')]);

        return back();
    }
}
