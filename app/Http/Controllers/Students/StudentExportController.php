<?php

namespace App\Http\Controllers\Students;

use App\Domain\Identity\Models\Identity;
use App\Domain\Shared\CalendarDate;
use App\Domain\Shared\CsvCell;
use App\Domain\Students\Actions\ExportStudents;
use App\Domain\Students\Data\StudentFilters;
use App\Domain\Students\StudentSession;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use SplFileObject;
use Symfony\Component\HttpFoundation\StreamedResponse;

class StudentExportController extends Controller
{
    /**
     * Downloads the students matching the list's filters as CSV (audited).
     */
    public function __invoke(Request $request, ExportStudents $exportStudents, StudentSession $sessions): StreamedResponse
    {
        Gate::authorize('students.export');

        $identity = $request->user();
        abort_unless($identity instanceof Identity, 403);

        $input = $request->validate([
            'q' => ['nullable', 'string', 'max:100'],
            'class' => ['nullable', 'string', 'max:26'],
            'status' => ['nullable', Rule::in(['current', 'left', 'all'])],
        ]);

        $campus = $request->session()->get('school.campus');
        $chosen = $request->session()->get('school.session');
        $session = $sessions->resolve($identity, is_string($chosen) ? $chosen : null);

        $rows = $exportStudents->handle($identity, new StudentFilters(
            search: (string) ($input['q'] ?? ''),
            sectionUlid: ($input['class'] ?? '') !== '' ? $input['class'] : null,
            status: $input['status'] ?? 'current',
            campusUlid: is_string($campus) && $campus !== '' ? $campus : null,
            yearId: $session === null || $session['is_current'] ? null : $session['key'],
        ));

        return response()->streamDownload(function () use ($rows) {
            $out = new SplFileObject('php://output', 'w');
            $out->fwrite("\xEF\xBB\xBF");

            foreach ($rows as $row) {
                $out->fputcsv(array_map(CsvCell::safe(...), $row), ',', '"', '');
            }
        }, 'students-'.CalendarDate::today().'.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }
}
