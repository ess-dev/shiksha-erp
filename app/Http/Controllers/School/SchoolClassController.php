<?php

namespace App\Http\Controllers\School;

use App\Domain\Identity\Models\Identity;
use App\Domain\School\Actions\CreateSchoolClass;
use App\Http\Controllers\Controller;
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
}
