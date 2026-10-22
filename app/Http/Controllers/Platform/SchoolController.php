<?php

namespace App\Http\Controllers\Platform;

use App\Domain\Platform\Actions\ExportSchools;
use App\Domain\Platform\Queries\SchoolDetailQuery;
use App\Domain\Platform\Queries\SchoolsQuery;
use App\Domain\Shared\CalendarDate;
use App\Http\Controllers\Controller;
use App\Http\Requests\Platform\ListSchoolsRequest;
use App\Tenancy\Tenant;
use Inertia\Inertia;
use Inertia\Response;
use SplFileObject;
use Symfony\Component\HttpFoundation\StreamedResponse;

class SchoolController extends Controller
{
    public function index(ListSchoolsRequest $request, SchoolsQuery $schools): Response
    {
        return Inertia::render('platform/schools', [
            'schools' => $schools->list($request->status(), $request->planUlid(), $request->search(), $request->integer('page', 1)),
            'filters' => [
                'status' => $request->status(),
                'plan' => $request->planUlid(),
                'q' => $request->search(),
            ],
        ]);
    }

    public function show(Tenant $school, SchoolDetailQuery $detail): Response
    {
        return Inertia::render('platform/school-detail', [
            'school' => $detail->get($school),
        ]);
    }

    public function export(ListSchoolsRequest $request, ExportSchools $export): StreamedResponse
    {
        $rows = $export->handle($request->status(), $request->planUlid(), $request->search());

        return response()->streamDownload(function () use ($rows) {
            $out = new SplFileObject('php://output', 'w');

            foreach ($rows as $row) {
                $out->fputcsv(array_map($this->safeCell(...), $row), ',', '"', '');
            }
        }, 'schools-'.CalendarDate::today().'.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    /**
     * Stops spreadsheet programs from running a cell that starts like a formula.
     */
    private function safeCell(string $value): string
    {
        return $value !== '' && in_array($value[0], ['=', '+', '-', '@', "\t", "\r"], true) ? "'".$value : $value;
    }
}
