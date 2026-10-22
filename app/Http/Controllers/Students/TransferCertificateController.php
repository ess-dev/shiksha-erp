<?php

namespace App\Http\Controllers\Students;

use App\Domain\Identity\Models\Identity;
use App\Domain\Students\Actions\IssueTransferCertificate;
use App\Domain\Students\Enums\TransferReason;
use App\Domain\Students\Models\Student;
use App\Domain\Students\Queries\TransferCertificateQuery;
use App\Http\Controllers\Controller;
use App\Http\Requests\Students\IssueTransferCertificateRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class TransferCertificateController extends Controller
{
    /**
     * Issues the TC, then opens its printable page.
     */
    public function store(IssueTransferCertificateRequest $request, Student $student, IssueTransferCertificate $issue): RedirectResponse
    {
        $identity = $request->user();
        abort_unless($identity instanceof Identity, 403);

        $issue->handle($identity, $student, $request->string('last_day')->toString(), TransferReason::from($request->string('reason')->toString()));

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Transfer certificate issued.')]);

        return to_route('students.tc.show', $student);
    }

    /**
     * A print-friendly page; the browser's Print or Save as PDF does the rest.
     */
    public function show(Request $request, Student $student, TransferCertificateQuery $certificate): Response
    {
        $identity = $request->user();
        abort_unless($identity instanceof Identity, 403);

        return Inertia::render('students/transfer-certificate', [
            'certificate' => $certificate->get($identity, $student),
            'student_ulid' => $student->ulid,
        ]);
    }
}
