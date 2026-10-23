<?php

namespace App\Domain\Admissions\Queries;

use App\Domain\Admissions\AdmissionVisibility;
use App\Domain\Admissions\Data\AdmissionFunnelData;
use App\Domain\Admissions\Data\StageData;
use App\Domain\Admissions\Enums\AdmissionStage;
use App\Domain\Admissions\Models\AdmissionApplication;
use App\Domain\Identity\Models\Identity;
use App\Domain\School\Queries\SectionDirectoryQuery;
use App\Domain\Shared\CalendarDate;
use Carbon\CarbonImmutable;

class AdmissionFunnelQuery
{
    public function __construct(
        private readonly AdmissionVisibility $visibility,
        private readonly SectionDirectoryQuery $sections,
    ) {}

    /**
     * The current admission cycle as the admissions board counts it (this
     * academic year's applications plus any still open from earlier years),
     * within the person's `admissions.view` scope and the top-bar campus.
     * Null without the permission.
     */
    public function get(Identity $identity, ?string $campusUlid): ?AdmissionFunnelData
    {
        $query = $this->visibility->query($identity, 'admissions.view');

        if ($query === null) {
            return null;
        }

        if ($campusUlid !== null) {
            $query->where('c.ulid', $campusUlid);
        }

        $yearKey = $this->sections->currentYearRef()['key'] ?? null;

        if ($yearKey !== null) {
            $query->where(fn ($where) => $where
                ->where('admission_applications.academic_year_id', $yearKey)
                ->orWhere(fn ($open) => $open->where('admission_applications.is_closed', false)->where('admission_applications.stage', '!=', AdmissionStage::Enrolled->value)));
        }

        $rows = $query->get();
        $weekAgo = CarbonImmutable::parse(CalendarDate::today())->subDays(7)->toDateString();
        $reached = array_fill_keys(array_map(fn (AdmissionStage $stage) => $stage->value, AdmissionStage::cases()), 0);
        $open = 0;
        $new = 0;

        foreach ($rows as $row) {
            /** @var AdmissionApplication $row */
            if ($row->stage !== AdmissionStage::Enrolled && ! $row->is_closed) {
                $open++;
            }

            if ($row->enquired_on > $weekAgo) {
                $new++;
            }

            foreach (AdmissionStage::cases() as $stage) {
                if ($row->stage->position() >= $stage->position()) {
                    $reached[$stage->value]++;
                }
            }
        }

        $total = count($rows);
        $enrolled = $reached[AdmissionStage::Enrolled->value];

        return new AdmissionFunnelData(
            open_applications: $open,
            new_this_week: $new,
            conversion_percent: $total === 0 ? 0 : intdiv($enrolled * 200 + $total, $total * 2),
            stages: array_map(
                fn (AdmissionStage $stage) => new StageData($stage->value, $stage->label(), $reached[$stage->value]),
                AdmissionStage::cases(),
            ),
        );
    }
}
