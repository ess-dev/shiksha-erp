<?php

namespace App\Http\Controllers\School;

use App\Domain\Identity\Models\Identity;
use App\Domain\School\Actions\CreateCampus;
use App\Domain\School\Actions\UpdateCampus;
use App\Domain\School\Models\Campus;
use App\Http\Controllers\Controller;
use App\Http\Requests\School\StoreCampusRequest;
use App\Http\Requests\School\UpdateCampusRequest;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;

class CampusController extends Controller
{
    public function store(StoreCampusRequest $request, CreateCampus $createCampus): RedirectResponse
    {
        $identity = $request->user();
        abort_unless($identity instanceof Identity, 403);

        $createCampus->handle($identity, $request->string('name')->toString(), $request->string('code')->toString());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Campus added.')]);

        return back();
    }

    public function update(UpdateCampusRequest $request, Campus $campus, UpdateCampus $updateCampus): RedirectResponse
    {
        $identity = $request->user();
        abort_unless($identity instanceof Identity, 403);

        $updateCampus->handle($identity, $campus, $request->string('name')->toString(), $request->string('status')->toString());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Campus updated.')]);

        return back();
    }
}
