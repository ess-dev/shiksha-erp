<?php

namespace App\Http\Controllers\School;

use App\Domain\Identity\Models\Identity;
use App\Domain\School\Actions\CreateClassesInBulk;
use App\Domain\School\Actions\CreateSchoolClass;
use App\Http\Controllers\Controller;
use App\Http\Requests\School\BulkStoreSchoolClassesRequest;
use App\Http\Requests\School\StoreSchoolClassRequest;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;

class SchoolClassController extends Controller
{
    public function store(StoreSchoolClassRequest $request, CreateSchoolClass $createClass): RedirectResponse
    {
        $identity = $request->user();
        abort_unless($identity instanceof Identity, 403);

        $createClass->handle($identity, $request->string('name')->toString(), $request->integer('level'));

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Class added.')]);

        return back();
    }

    public function storeBulk(BulkStoreSchoolClassesRequest $request, CreateClassesInBulk $createInBulk): RedirectResponse
    {
        $identity = $request->user();
        abort_unless($identity instanceof Identity, 403);

        $result = $createInBulk->handle(
            $identity,
            $request->academicYear(),
            $request->campus(),
            $request->integer('from_level'),
            $request->integer('to_level'),
            $request->integer('sections'),
            $request->string('naming')->toString() === 'letters',
            $request->filled('capacity') ? $request->integer('capacity') : null,
            $request->overrides(),
        );

        Inertia::flash('toast', ['type' => 'success', 'message' => __(':classes classes and :sections sections added (:skipped already existed).', [
            'classes' => $result['classes_created'],
            'sections' => $result['sections_created'],
            'skipped' => $result['sections_skipped'],
        ])]);

        return back();
    }
}
