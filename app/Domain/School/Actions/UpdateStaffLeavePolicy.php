<?php

namespace App\Domain\School\Actions;

use App\Domain\Access\Queries\PermissionScopesQuery;
use App\Domain\Audit\Data\AuditEntry;
use App\Domain\Identity\Models\Identity;
use App\Domain\School\Exceptions\CalendarException;
use App\Domain\School\Models\SchoolSetting;
use App\Domain\School\Queries\SchoolSettingsQuery;
use App\Domain\Shared\Action;

class UpdateStaffLeavePolicy extends Action
{
    public function __construct(
        private readonly PermissionScopesQuery $scopes,
        private readonly SchoolSettingsQuery $settings,
    ) {}

    /**
     * Saves the yearly allowance of each staff leave type (school days, empty
     * = as needed) and after how many days a certificate is needed. Applies to
     * requests made after the change. Needs `settings.manage`.
     *
     * @param  array<string, array{days: int|null, document_after_days: int|null}>  $policy  by leave type key; the Leave module decides which types exist
     */
    public function handle(Identity $actor, array $policy): void
    {
        if (! $this->scopes->get($actor, 'settings.manage')->allows()) {
            throw CalendarException::notAllowed();
        }

        $clean = [];

        foreach ($policy as $type => $row) {
            $days = $row['days'];
            $after = $row['document_after_days'];

            if (($days !== null && ($days < 1 || $days > 365)) || ($after !== null && ($after < 0 || $after > 30))) {
                throw CalendarException::invalidStaffLeavePolicy();
            }

            $clean[$type] = ['days' => $days, 'document_after_days' => $after];
        }

        $this->transaction(function () use ($clean) {
            $before = $this->settings->staffLeavePolicy();

            SchoolSetting::query()->updateOrCreate(['key' => SchoolSettingsQuery::STAFF_LEAVE_POLICY], ['value' => $clean]);

            $this->audit(new AuditEntry('settings.staff_leave_policy_updated', 'school_settings', null, $before, $clean));
        });
    }
}
