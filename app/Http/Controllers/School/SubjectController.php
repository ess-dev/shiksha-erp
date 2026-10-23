<?php

namespace App\Http\Controllers\School;

use App\Domain\Identity\Models\Identity;
use App\Domain\School\Actions\CreateSubject;
use App\Http\Controllers\Controller;
use App\Http\Requests\School\StoreSubjectRequest;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;

class SubjectController extends Controller
{
    public function store(StoreSubjectRequest $request, CreateSubject $createSubject): RedirectResponse
    {
        $identity = $request->user();
        abort_unless($identity instanceof Identity, 403);

        $createSubject->handle($identity, $request->string('name')->toString(), $request->string('code')->toString());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Subject added.')]);

        return back();
    }
}
