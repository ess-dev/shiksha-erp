<?php

namespace App\Domain\School\Actions;

use App\Domain\Access\Queries\PermissionScopesQuery;
use App\Domain\Audit\Data\AuditEntry;
use App\Domain\Identity\Models\Identity;
use App\Domain\School\Exceptions\CalendarException;
use App\Domain\School\Models\SchoolSetting;
use App\Domain\School\Queries\SchoolSettingsQuery;
use App\Domain\Shared\Action;

class UpdateAttendanceCalendar extends Action
{
    public function __construct(
        private readonly PermissionScopesQuery $scopes,
        private readonly SchoolSettingsQuery $settings,
    ) {}

    /**
     * Saves the weekly working days (ISO 1 to 7) and how many days a class
     * teacher may still change a register. Needs `settings.manage`.
     *
     * @param  list<int>  $workingDays
     */
    public function handle(Identity $actor, array $workingDays, int $correctionWindowDays): void
    {
        if (! $this->scopes->get($actor, 'settings.manage')->allows()) {
            throw CalendarException::notAllowed();
        }

        $workingDays = array_values(array_unique($workingDays));
        sort($workingDays);

        if ($workingDays === [] || array_diff($workingDays, [1, 2, 3, 4, 5, 6, 7]) !== []) {
            throw CalendarException::invalidWorkingDays();
        }

        if ($correctionWindowDays < 0 || $correctionWindowDays > 60) {
            throw CalendarException::invalidWindow();
        }

        $this->transaction(function () use ($workingDays, $correctionWindowDays) {
            $before = [
                'working_days' => $this->settings->attendanceWorkingDays(),
                'correction_window_days' => $this->settings->attendanceCorrectionWindowDays(),
            ];

            SchoolSetting::query()->updateOrCreate(['key' => SchoolSettingsQuery::ATTENDANCE_WORKING_DAYS], ['value' => $workingDays]);
            SchoolSetting::query()->updateOrCreate(['key' => SchoolSettingsQuery::ATTENDANCE_CORRECTION_DAYS], ['value' => $correctionWindowDays]);

            $this->audit(new AuditEntry('settings.attendance_calendar_updated', 'school_settings', null, $before, [
                'working_days' => $workingDays,
                'correction_window_days' => $correctionWindowDays,
            ]));
        });
    }
}
