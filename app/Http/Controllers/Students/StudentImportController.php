<?php

namespace App\Http\Controllers\Students;

use App\Domain\Identity\Models\Identity;
use App\Domain\Shared\CsvCell;
use App\Domain\Students\Actions\ImportStudents;
use App\Domain\Students\CsvTable;
use App\Domain\Students\Queries\StudentImportQuery;
use App\Http\Controllers\Controller;
use App\Http\Requests\Students\ImportStudentsRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use SplFileObject;
use Symfony\Component\HttpFoundation\StreamedResponse;

class StudentImportController extends Controller
{
    /**
     * The heading row and one example row, as a CSV to fill in.
     */
    public function template(StudentImportQuery $import): StreamedResponse
    {
        Gate::authorize('students.import');

        return response()->streamDownload(function () use ($import) {
            $out = new SplFileObject('php://output', 'w');
            $out->fwrite("\xEF\xBB\xBF");

            foreach ($import->template() as $row) {
                $out->fputcsv(array_map(CsvCell::safe(...), $row), ',', '"', '');
            }
        }, 'student-import-template.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    /**
     * Checks the uploaded file and says what an import would do; nothing is saved.
     */
    public function preview(ImportStudentsRequest $request, StudentImportQuery $import): JsonResponse
    {
        $identity = $request->user();
        abort_unless($identity instanceof Identity, 403);

        $file = $request->file('file');
        $table = $this->table($request);

        return response()->json($import->preview($identity, $table, $file->getClientOriginalName())->toArray());
    }

    /**
     * Imports the rows that pass the checks (the file is checked again here).
     */
    public function store(ImportStudentsRequest $request, ImportStudents $importStudents): RedirectResponse
    {
        $identity = $request->user();
        abort_unless($identity instanceof Identity, 403);

        $result = $importStudents->handle($identity, $this->table($request), $request->file('file')->getClientOriginalName());

        Inertia::flash('import_result', $result->toArray());

        return to_route('students.index');
    }

    /**
     * @return list<list<string>>
     */
    private function table(Request $request): array
    {
        $table = CsvTable::read($request->file('file')->getRealPath());

        if (count($table) < 2) {
            throw ValidationException::withMessages(['file' => __('That file has no student rows.')]);
        }

        if (count($table) - 1 > StudentImportQuery::MAX_ROWS) {
            throw ValidationException::withMessages(['file' => __('Up to 1,000 students per file. Split it into smaller files.')]);
        }

        return $table;
    }
}
