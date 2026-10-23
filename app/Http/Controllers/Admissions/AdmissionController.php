<?php

namespace App\Http\Controllers\Admissions;

use App\Domain\Admissions\Actions\AddEnquiry;
use App\Domain\Admissions\Data\NewEnquiry;
use App\Domain\Admissions\Enums\AdmissionSource;
use App\Domain\Admissions\Queries\AdmissionBoardQuery;
use App\Domain\Admissions\Queries\AdmissionDetailQuery;
use App\Domain\Admissions\Queries\AdmissionOptionsQuery;
use App\Domain\Identity\Models\Identity;
use App\Domain\Shared\CalendarDate;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admissions\StoreEnquiryRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class AdmissionController extends Controller
{
    /**
     * The admissions board. `?open=<ulid>` also loads that application for
     * the details dialog (and the section choices when it can be enrolled).
     */
    public function index(Request $request, AdmissionBoardQuery $board, AdmissionDetailQuery $detail, AdmissionOptionsQuery $options): Response
    {
        Gate::authorize('admissions.view');

        $identity = $request->user();
        abort_unless($identity instanceof Identity, 403);

        $campus = $request->session()->get('school.campus');
        $campus = is_string($campus) && $campus !== '' ? $campus : null;
        $showClosed = $request->boolean('closed');

        $open = $request->query('open');
        $open = is_string($open) && $open !== '' ? $open : null;

        $selectedDetail = $open === null ? null : $detail->find($identity, $open);

        return Inertia::render('admissions/index', [
            'board' => $board->get($identity, $campus, $showClosed),
            'abilities' => $options->abilities($identity),
            'options' => $options->get($identity),
            'show_closed' => $showClosed,
            'today' => CalendarDate::today(),
            'selected' => $selectedDetail,
            'enrol_sections' => $selectedDetail !== null && $selectedDetail->decision === 'accepted' && ! $selectedDetail->is_locked
                ? $options->enrolSections($identity, $selectedDetail->ulid)
                : [],
        ]);
    }

    public function store(StoreEnquiryRequest $request, AddEnquiry $addEnquiry): RedirectResponse
    {
        $identity = $request->user();
        abort_unless($identity instanceof Identity, 403);

        $application = $addEnquiry->handle($identity, new NewEnquiry(
            firstName: $request->string('first_name')->toString(),
            lastName: $request->string('last_name')->toString(),
            gender: $request->string('gender')->toString(),
            bornOn: $request->filled('born_on') ? $request->string('born_on')->toString() : null,
            classUlid: $request->string('class')->toString(),
            campusUlid: $request->string('campus')->toString(),
            parentName: $request->string('parent_name')->toString(),
            parentRelation: $request->string('relation')->toString(),
            phone: $request->string('phone')->toString(),
            source: AdmissionSource::from($request->string('source')->toString()),
            notes: $request->filled('notes') ? $request->string('notes')->toString() : null,
        ));

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Enquiry added for :name.', ['name' => $application->first_name])]);

        return back();
    }
}
