<?php

namespace App\Http\Controllers\School;

use App\Domain\Identity\Models\Identity;
use App\Domain\School\Actions\CreateSection;
use App\Http\Controllers\Controller;
use App\Http\Requests\School\StoreSectionRequest;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;

class SectionController extends Controller
{
    public function store(StoreSectionRequest $request, CreateSection $createSection): RedirectResponse
    {
        $identity = $request->user();
        abort_unless($identity instanceof Identity, 403);

        $createSection->handle(
            $identity,
            $request->academicYear(),
            $request->campus(),
            $request->schoolClass(),
            $request->string('name')->toString(),
            $request->filled('capacity') ? $request->integer('capacity') : null,
        );

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Section added.')]);

        return back();
    }
}
