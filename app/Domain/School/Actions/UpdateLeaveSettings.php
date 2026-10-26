<?php

namespace App\Domain\School\Actions;

use App\Domain\Access\Queries\PermissionScopesQuery;
use App\Domain\Audit\Data\AuditEntry;
use App\Domain\Identity\Models\Identity;
use App\Domain\School\Exceptions\CalendarException;
use App\Domain\School\Models\SchoolSetting;
use App\Domain\School\Queries\SchoolSettingsQuery;
use App\Domain\Shared\Action;

class UpdateLeaveSettings extends Action
{
    public function __construct(
        private readonly PermissionScopesQuery $scopes,
        private readonly SchoolSettingsQuery $settings,
    ) {}

    /**
     * Saves how many school days of student leave the class teacher may
     * approve; longer leave also goes to the coordinator. Applies to requests
     * made after the change. Needs `settings.manage`.
     */
    public function handle(Identity $actor, int $escalationDays): void
    {
        if (! $this->scopes->get($actor, 'settings.manage')->allows()) {
            throw CalendarException::notAllowed();
        }

        if ($escalationDays < 1 || $escalationDays > 30) {
            throw CalendarException::invalidLeaveLimit();
        }

        $this->transaction(function () use ($escalationDays) {
            $before = ['escalation_days' => $this->settings->leaveEscalationDays()];

            SchoolSetting::query()->updateOrCreate(['key' => SchoolSettingsQuery::LEAVE_ESCALATION_DAYS], ['value' => $escalationDays]);

            $this->audit(new AuditEntry('settings.leave_updated', 'school_settings', null, $before, ['escalation_days' => $escalationDays]));
        });
    }
}
