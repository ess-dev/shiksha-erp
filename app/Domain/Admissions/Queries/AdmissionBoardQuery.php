<?php

namespace App\Domain\Admissions\Queries;

use App\Domain\Admissions\AdmissionDocumentRules;
use App\Domain\Admissions\AdmissionVisibility;
use App\Domain\Admissions\Data\ApplicationCardData;
use App\Domain\Admissions\Data\BoardData;
use App\Domain\Admissions\Data\BoardSummaryData;
use App\Domain\Admissions\Data\StageData;
use App\Domain\Admissions\Enums\AdmissionStage;
use App\Domain\Admissions\Exceptions\AdmissionException;
use App\Domain\Admissions\Models\AdmissionApplication;
use App\Domain\Admissions\Models\AdmissionDocument;
use App\Domain\Identity\Models\Identity;
use App\Domain\School\Queries\SectionDirectoryQuery;
use App\Domain\Shared\CalendarDate;
use Carbon\CarbonImmutable;

class AdmissionBoardQuery
{
    public function __construct(
        private readonly AdmissionVisibility $visibility,
        private readonly AdmissionDocumentRules $rules,
        private readonly SectionDirectoryQuery $sections,
    ) {}

    /**
     * The pipeline board: totals, a count per stage and one card per
     * application the person may see, newest enquiry first. Applications of
     * the current academic year are listed, plus any still open from earlier
     * years. Refused ("Not admitted") applications show only when asked for.
     * `$campusUlid` narrows to one campus (the top-bar switcher).
     */
    public function get(Identity $identity, ?string $campusUlid, bool $showClosed): BoardData
    {
        $query = $this->visibility->query($identity, 'admissions.view') ?? throw AdmissionException::notAllowed();

        if ($campusUlid !== null) {
            $query->where('c.ulid', $campusUlid);
        }

        $yearKey = $this->sections->currentYearRef()['key'] ?? null;

        if ($yearKey !== null) {
            $query->where(fn ($where) => $where
                ->where('admission_applications.academic_year_id', $yearKey)
                ->orWhere(fn ($open) => $open->where('admission_applications.is_closed', false)->where('admission_applications.stage', '!=', AdmissionStage::Enrolled->value)));
        }

        $rows = $query
            ->leftJoin('students as st', 'st.id', '=', 'admission_applications.student_id')
            ->addSelect(['c.name as campus_name', 'sc.name as class_name', 'sc.ulid as class_ulid', 'st.ulid as student_ulid'])
            ->orderByDesc('admission_applications.enquired_on')
            ->orderByDesc('admission_applications.id')
            ->get();

        $received = AdmissionDocument::query()
            ->whereIn('admission_application_id', $rows->pluck('id'))
            ->where('is_received', true)
            ->get()
            ->groupBy('admission_application_id')
            ->map(fn ($documents) => $documents->map(fn (AdmissionDocument $document) => $document->kind->value)->all());

        $today = CarbonImmutable::parse(CalendarDate::today());
        $weekAgo = $today->subDays(7)->toDateString();

        $cards = [];
        $counts = array_fill_keys(array_map(fn (AdmissionStage $stage) => $stage->value, AdmissionStage::cases()), 0);
        $enrolled = 0;
        $open = 0;
        $new = 0;

        foreach ($rows as $row) {
            /** @var AdmissionApplication $row */
            if ($row->stage === AdmissionStage::Enrolled) {
                $enrolled++;
            } elseif (! $row->is_closed) {
                $open++;
            }

            if ($row->enquired_on > $weekAgo) {
                $new++;
            }

            if (! $row->is_closed) {
                $counts[$row->stage->value]++;
            }

            if ($row->is_closed && ! $showClosed) {
                continue;
            }

            $missing = 0;

            if ($row->stage->position() <= AdmissionStage::Application->position()) {
                $have = $received[$row->id] ?? [];
                $missing = count(array_filter(
                    $this->rules->requiredFor((string) $row->getAttribute('class_ulid')),
                    fn ($kind) => ! in_array($kind->value, $have, true),
                ));
            }

            $cards[] = new ApplicationCardData(
                ulid: $row->ulid,
                name: $row->name(),
                class_name: (string) $row->getAttribute('class_name'),
                campus_name: self::shortCampus((string) $row->getAttribute('campus_name')),
                source: $row->source->label(),
                parent_name: $row->parent_name,
                stage: $row->stage->value,
                decision: $row->decision?->value,
                is_closed: $row->is_closed,
                is_locked: $row->isLocked(),
                days: abs((int) $today->diffInDays(CarbonImmutable::parse($row->enquired_on), false)),
                missing_documents: $missing,
                student_ulid: $row->getAttribute('student_ulid'),
            );
        }

        $total = count($rows);

        return new BoardData(
            summary: new BoardSummaryData(
                open_applications: $open,
                new_this_week: $new,
                enrolled: $enrolled,
                // Whole percent, half up, in integers.
                conversion_percent: $total === 0 ? 0 : intdiv($enrolled * 200 + $total, $total * 2),
            ),
            stages: array_map(
                fn (AdmissionStage $stage) => new StageData($stage->value, $stage->label(), $counts[$stage->value]),
                AdmissionStage::cases(),
            ),
            cards: $cards,
        );
    }

    /** The cards show "Mohali", not "Mohali campus". */
    private static function shortCampus(string $name): string
    {
        $short = trim((string) preg_replace('/\s+campus$/i', '', $name));

        return $short === '' ? $name : $short;
    }
}
