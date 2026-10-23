<?php

namespace App\Http\Controllers\School;

use App\Domain\Identity\Models\Identity;
use App\Domain\School\Actions\AddSubject;
use App\Domain\School\Actions\DeleteSubject;
use App\Domain\School\Actions\UpdateSubject;
use App\Domain\School\Models\Subject;
use App\Http\Controllers\Controller;
use App\Http\Requests\School\SaveSubjectRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;

class SubjectCatalogController extends Controller
{
    public function store(SaveSubjectRequest $request, AddSubject $addSubject): RedirectResponse
    {
        $subject = $addSubject->handle(
            $this->actor($request),
            $request->string('name')->toString(),
            $request->string('code')->toString(),
            $request->subjectType(),
            $request->classes(),
        );

        Inertia::flash('toast', ['type' => 'success', 'message' => __(':name added.', ['name' => $subject->name])]);

        return back();
    }

    public function update(SaveSubjectRequest $request, Subject $subject, UpdateSubject $updateSubject): RedirectResponse
    {
        $updateSubject->handle(
            $this->actor($request),
            $subject,
            $request->string('name')->toString(),
            $request->string('code')->toString(),
            $request->subjectType(),
        );

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Saved.')]);

        return back();
    }

    public function destroy(Request $request, Subject $subject, DeleteSubject $deleteSubject): RedirectResponse
    {
        $deleteSubject->handle($this->actor($request), $subject);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Deleted.')]);

        return back();
    }

    private function actor(Request $request): Identity
    {
        $identity = $request->user();
        abort_unless($identity instanceof Identity, 403);

        return $identity;
    }
}
