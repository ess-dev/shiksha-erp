<?php

namespace App\Http\Controllers\Students;

use App\Domain\Identity\Models\Identity;
use App\Domain\Students\Actions\OpenStudentDocument;
use App\Domain\Students\Actions\UploadStudentDocument;
use App\Domain\Students\Actions\VerifyStudentDocument;
use App\Domain\Students\Enums\DocumentKind;
use App\Domain\Students\Models\Student;
use App\Domain\Students\Models\StudentDocument;
use App\Http\Controllers\Controller;
use App\Http\Requests\Students\UploadStudentDocumentRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\StreamedResponse;

class StudentDocumentController extends Controller
{
    public function store(UploadStudentDocumentRequest $request, Student $student, UploadStudentDocument $upload): RedirectResponse
    {
        $identity = $request->user();
        abort_unless($identity instanceof Identity, 403);

        $upload->handle($identity, $student, DocumentKind::from($request->string('kind')->toString()), $request->file('file'));

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Document uploaded and verified.')]);

        return back();
    }

    public function verify(Request $request, StudentDocument $document, VerifyStudentDocument $verify): RedirectResponse
    {
        Gate::authorize('students.documents');

        $identity = $request->user();
        abort_unless($identity instanceof Identity, 403);

        $verify->handle($identity, $document);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Marked as verified.')]);

        return back();
    }

    /**
     * Streams the file after the permission check, from a short-lived signed
     * link (never a public path). Every opening is audited.
     */
    public function show(Request $request, StudentDocument $document, OpenStudentDocument $open): StreamedResponse
    {
        $identity = $request->user();
        abort_unless($identity instanceof Identity, 403);

        $path = $open->handle($identity, $document);

        return Storage::disk('tenant')->response($path, $document->original_name, [
            'Content-Type' => $document->mime_type,
            'X-Content-Type-Options' => 'nosniff',
            'Cache-Control' => 'private, no-store',
        ]);
    }
}
