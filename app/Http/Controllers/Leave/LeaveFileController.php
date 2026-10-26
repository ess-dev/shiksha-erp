<?php

namespace App\Http\Controllers\Leave;

use App\Domain\Identity\Models\Identity;
use App\Domain\Leave\Actions\OpenLeaveAttachment;
use App\Domain\Leave\Queries\LeaveFileQuery;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class LeaveFileController extends Controller
{
    /**
     * A child's medical certificate or letter, for staff covering the
     * student's section, from a short-lived signed link. Every opening is
     * audited. Photos and PDFs open in the browser. Never cached, never sniffed.
     */
    public function attachment(Request $request, string $leave, LeaveFileQuery $files, OpenLeaveAttachment $open): StreamedResponse
    {
        $actor = $request->user();
        abort_unless($actor instanceof Identity, 403);

        $found = $files->forStaff($actor, $leave);
        $path = $open->handle($found);

        return Storage::disk('tenant')->response($path, (string) $found->attachment_name, [
            'Content-Type' => (string) $found->attachment_mime,
            'X-Content-Type-Options' => 'nosniff',
            'Cache-Control' => 'private, no-store',
        ]);
    }
}
