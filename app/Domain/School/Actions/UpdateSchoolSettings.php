<?php

namespace App\Domain\School\Actions;

use App\Domain\Access\Queries\PermissionScopesQuery;
use App\Domain\Audit\Data\AuditEntry;
use App\Domain\Identity\Models\Identity;
use App\Domain\School\Exceptions\HouseException;
use App\Domain\School\Models\SchoolSetting;
use App\Domain\School\Queries\SchoolSettingsQuery;
use App\Domain\Shared\Action;

class UpdateSchoolSettings extends Action
{
    public function __construct(private readonly PermissionScopesQuery $scopes) {}

    /**
     * Saves the admission-number and TC-number prefixes. Needs `settings.manage`.
     */
    public function handle(Identity $actor, string $admissionPrefix, string $tcPrefix): void
    {
        if (! $this->scopes->get($actor, 'settings.manage')->allows()) {
            throw HouseException::notAllowed();
        }

        $values = [
            SchoolSettingsQuery::ADMISSION_PREFIX => strtoupper(trim($admissionPrefix)),
            SchoolSettingsQuery::TC_PREFIX => strtoupper(trim($tcPrefix)),
        ];

        foreach ($values as $prefix) {
            if (preg_match('/^[A-Z0-9-]{1,12}$/', $prefix) !== 1) {
                throw HouseException::invalidPrefix();
            }
        }

        $this->transaction(function () use ($values) {
            $before = [];

            foreach ($values as $key => $value) {
                $before[$key] = SchoolSetting::query()->where('key', $key)->value('value');

                SchoolSetting::query()->updateOrCreate(['key' => $key], ['value' => $value]);
            }

            $this->audit(new AuditEntry('settings.student_numbers_updated', 'school_settings', null, $before, $values));
        });
    }
}
