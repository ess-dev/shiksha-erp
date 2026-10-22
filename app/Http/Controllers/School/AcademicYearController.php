<?php

namespace App\Http\Controllers\School;

use App\Domain\Identity\Models\Identity;
use App\Domain\School\Actions\CreateAcademicYear;
use App\Domain\School\Actions\SetCurrentAcademicYear;
use App\Domain\School\Models\AcademicYear;
use App\Http\Controllers\Controller;
use App\Http\Requests\School\StoreAcademicYearRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;

class AcademicYearController extends Controller
{
    public function store(StoreAcademicYearRequest $request, CreateAcademicYear $createYear): RedirectResponse
    {
        $identity = $request->user();
        abort_unless($identity instanceof Identity, 403);

        $createYear->handle(
            $identity,
            $request->string('name')->toString(),
            $request->string('starts_on')->toString(),
            $request->string('ends_on')->toString(),
        );

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Academic year added.')]);

        return back();
    }

    public function makeCurrent(Request $request, AcademicYear $academicYear, SetCurrentAcademicYear $setCurrent): RedirectResponse
    {
        Gate::authorize('structure.manage');

        $identity = $request->user();
        abort_unless($identity instanceof Identity, 403);

        $setCurrent->handle($identity, $academicYear);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Current academic year changed.')]);

        return back();
    }
}
