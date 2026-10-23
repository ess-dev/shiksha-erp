<?php

namespace App\Http\Controllers\Admissions;

use App\Domain\Admissions\Actions\EnrollApplicant;
use App\Domain\Admissions\Actions\MoveApplication;
use App\Domain\Admissions\Actions\RecordDecision;
use App\Domain\Admissions\Actions\SaveApplicationNotes;
use App\Domain\Admissions\Actions\UpdateApplicationDetails;
use App\Domain\Admissions\Data\ApplicationDetailsUpdate;
use App\Domain\Admissions\Enums\AdmissionDecision;
use App\Domain\Admissions\Enums\AdmissionStage;
use App\Domain\Admissions\Models\AdmissionApplication;
use App\Domain\Identity\Models\Identity;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admissions\EnrolApplicantRequest;
use App\Http\Requests\Admissions\MoveApplicationRequest;
use App\Http\Requests\Admissions\RecordDecisionRequest;
use App\Http\Requests\Admissions\UpdateDetailsRequest;
use App\Http\Requests\Admissions\UpdateNotesRequest;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;

class ApplicationController extends Controller
{
    public function move(MoveApplicationRequest $request, AdmissionApplication $application, MoveApplication $move): RedirectResponse
    {
        $identity = $request->user();
        abort_unless($identity instanceof Identity, 403);

        $stage = AdmissionStage::from($request->string('stage')->toString());

        $move->handle($identity, $application, $stage, $request->boolean('accept_missing_documents'));

        Inertia::flash('toast', ['type' => 'success', 'message' => __(':name moved to :stage.', ['name' => $application->first_name, 'stage' => $stage->label()])]);

        return back();
    }

    public function details(UpdateDetailsRequest $request, AdmissionApplication $application, UpdateApplicationDetails $update): RedirectResponse
    {
        $identity = $request->user();
        abort_unless($identity instanceof Identity, 403);

        $update->handle($identity, $application, new ApplicationDetailsUpdate(
            bornOn: $request->filled('born_on') ? $request->string('born_on')->toString() : null,
            bloodGroup: $request->filled('blood_group') ? $request->string('blood_group')->toString() : null,
            address: $request->filled('address') ? $request->string('address')->toString() : null,
            houseUlid: $request->filled('house') ? $request->string('house')->toString() : null,
            parentEmail: $request->filled('email') ? $request->string('email')->toString() : null,
        ));

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Details saved.')]);

        return back();
    }

    public function notes(UpdateNotesRequest $request, AdmissionApplication $application, SaveApplicationNotes $save): RedirectResponse
    {
        $identity = $request->user();
        abort_unless($identity instanceof Identity, 403);

        $save->handle($identity, $application, $request->filled('notes') ? $request->string('notes')->toString() : null);

        return back();
    }

    public function decide(RecordDecisionRequest $request, AdmissionApplication $application, RecordDecision $record): RedirectResponse
    {
        $identity = $request->user();
        abort_unless($identity instanceof Identity, 403);

        $decision = AdmissionDecision::from($request->string('decision')->toString());

        $record->handle($identity, $application, $decision);

        $message = match ($decision) {
            AdmissionDecision::Accepted => __(':name accepted. Enroll when the family confirms.', ['name' => $application->first_name]),
            AdmissionDecision::Waitlisted => __('Added to the waitlist.'),
            AdmissionDecision::Rejected => __('Marked as not admitted.'),
        };

        Inertia::flash('toast', ['type' => $decision === AdmissionDecision::Rejected ? 'info' : 'success', 'message' => $message]);

        return back();
    }

    public function enrol(EnrolApplicantRequest $request, AdmissionApplication $application, EnrollApplicant $enroll): RedirectResponse
    {
        $identity = $request->user();
        abort_unless($identity instanceof Identity, 403);

        $enrolled = $enroll->handle($identity, $application, $request->string('section')->toString());

        Inertia::flash('enrolled', $enrolled->toArray());

        return to_route('admissions.index');
    }
}
