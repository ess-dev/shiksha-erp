<?php

namespace App\Domain\School\Actions;

use App\Domain\Access\Queries\PermissionScopesQuery;
use App\Domain\Audit\Data\AuditEntry;
use App\Domain\Identity\Models\Identity;
use App\Domain\School\Exceptions\CalendarException;
use App\Domain\School\Models\Holiday;
use App\Domain\Shared\Action;
use App\Domain\Shared\CalendarDate;
use Illuminate\Support\Str;
use InvalidArgumentException;

class CreateHoliday extends Action
{
    public function __construct(private readonly PermissionScopesQuery $scopes) {}

    /**
     * Adds a day the school is closed. Needs `settings.manage`. One holiday
     * per date; a register already saved for that date stays as it is.
     */
    public function handle(Identity $actor, string $date, string $name): Holiday
    {
        if (! $this->scopes->get($actor, 'settings.manage')->allows()) {
            throw CalendarException::notAllowed();
        }

        try {
            $date = CalendarDate::parse($date);
        } catch (InvalidArgumentException) {
            throw CalendarException::invalidDate();
        }

        $name = trim($name);

        if ($name === '' || mb_strlen($name) > 100) {
            throw CalendarException::invalidName();
        }

        return $this->transaction(function () use ($date, $name) {
            if (Holiday::query()->where('holiday_on', $date)->exists()) {
                throw CalendarException::duplicateHoliday($date);
            }

            $holiday = Holiday::query()->create([
                'ulid' => strtolower((string) Str::ulid()),
                'holiday_on' => $date,
                'name' => $name,
            ]);

            $this->audit(new AuditEntry('settings.holiday_added', 'holiday', $holiday->ulid, null, ['holiday_on' => $date, 'name' => $name]));

            return $holiday;
        });
    }
}
