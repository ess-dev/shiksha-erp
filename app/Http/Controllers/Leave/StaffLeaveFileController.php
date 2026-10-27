<?php

namespace App\Http\Controllers\Leave;

use App\Domain\Identity\Models\Identity;
use App\Domain\Leave\Actions\OpenStaffLeaveAttachment;
use App\Domain\Leave\Queries\StaffLeaveFileQuery;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class StaffLeaveFileController extends Controller
{
    /**
     * A staff member's medical certificate or letter, for the applicant or an
     * approver covering them, from a short-lived signed link. Every opening
     * is audited. Never cached, never sniffed.
     */
    public function attachment(Request $request, string $leave, StaffLeaveFileQuery $files, OpenStaffLeaveAttachment $open): StreamedResponse
    {
        $actor = $request->user();
        abort_unless($actor instanceof Identity, 403);

        $found = $files->forActor($actor, $leave);
        $path = $open->handle($found);

        return Storage::disk('tenant')->response($path, (string) $found->attachment_name, [
            'Content-Type' => (string) $found->attachment_mime,
            'X-Content-Type-Options' => 'nosniff',
            'Cache-Control' => 'private, no-store',
        ]);
    }
}
