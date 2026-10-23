<?php

namespace App\Http\Controllers\Admissions;

use App\Domain\Admissions\Actions\OpenAdmissionDocument;
use App\Domain\Admissions\Actions\SetDocumentReceived;
use App\Domain\Admissions\Actions\UploadAdmissionDocument;
use App\Domain\Admissions\Enums\AdmissionDocumentKind;
use App\Domain\Admissions\Models\AdmissionApplication;
use App\Domain\Admissions\Models\AdmissionDocument;
use App\Domain\Identity\Models\Identity;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admissions\SetDocumentReceivedRequest;
use App\Http\Requests\Admissions\UploadAdmissionDocumentRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ApplicationDocumentController extends Controller
{
    public function received(SetDocumentReceivedRequest $request, AdmissionApplication $application, string $kind, SetDocumentReceived $set): RedirectResponse
    {
        $identity = $request->user();
        abort_unless($identity instanceof Identity, 403);

        $set->handle($identity, $application, AdmissionDocumentKind::tryFrom($kind) ?? abort(404), $request->boolean('received'));

        return back();
    }

    public function store(UploadAdmissionDocumentRequest $request, AdmissionApplication $application, string $kind, UploadAdmissionDocument $upload): RedirectResponse
    {
        $identity = $request->user();
        abort_unless($identity instanceof Identity, 403);

        $upload->handle($identity, $application, AdmissionDocumentKind::tryFrom($kind) ?? abort(404), $request->file('file'));

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Document uploaded and marked received.')]);

        return back();
    }

    /**
     * Streams the scan from a short-lived signed link (never a public path)
     * after the permission check. Every opening is audited.
     */
    public function show(Request $request, AdmissionApplication $application, string $kind, OpenAdmissionDocument $open): StreamedResponse
    {
        $identity = $request->user();
        abort_unless($identity instanceof Identity, 403);

        $document = AdmissionDocument::query()
            ->where('admission_application_id', $application->id)
            ->where('kind', AdmissionDocumentKind::tryFrom($kind) ?? abort(404))
            ->firstOrFail();

        $path = $open->handle($identity, $document);

        return Storage::disk('tenant')->response($path, (string) $document->original_name, [
            'Content-Type' => (string) $document->mime_type,
            'X-Content-Type-Options' => 'nosniff',
            'Cache-Control' => 'private, no-store',
        ]);
    }
}
