<?php

namespace App\Domain\School\Actions;

use App\Domain\Access\Queries\PermissionScopesQuery;
use App\Domain\Audit\Data\AuditEntry;
use App\Domain\Identity\Models\Identity;
use App\Domain\School\Exceptions\CalendarException;
use App\Domain\School\Models\Holiday;
use App\Domain\Shared\Action;

class RemoveHoliday extends Action
{
    public function __construct(private readonly PermissionScopesQuery $scopes) {}

    /**
     * Takes a day off the holiday list (a list entry is configuration, not a
     * record of what happened, so it is removed; the audit trail keeps it).
     * Needs `settings.manage`.
     */
    public function handle(Identity $actor, Holiday $holiday): void
    {
        if (! $this->scopes->get($actor, 'settings.manage')->allows()) {
            throw CalendarException::notAllowed();
        }

        $this->transaction(function () use ($holiday) {
            $before = ['holiday_on' => $holiday->holiday_on, 'name' => $holiday->name];

            $holiday->delete();

            $this->audit(new AuditEntry('settings.holiday_removed', 'holiday', $holiday->ulid, $before));
        });
    }
}
