<?php

namespace App\Domain\School\Actions;

use App\Domain\Access\Queries\PermissionScopesQuery;
use App\Domain\Audit\Data\AuditEntry;
use App\Domain\Identity\Models\Identity;
use App\Domain\School\Exceptions\HouseException;
use App\Domain\School\Models\SchoolClass;
use App\Domain\School\Models\SchoolSetting;
use App\Domain\School\Queries\SchoolSettingsQuery;
use App\Domain\Shared\Action;

class UpdateAdmissionDocumentRules extends Action
{
    public function __construct(private readonly PermissionScopesQuery $scopes) {}

    /**
     * Saves which documents admissions require, per kind: every class (null) or
     * a list of class ulids. A kind left out is not required. Needs
     * `settings.manage`.
     *
     * @param  array<string, list<string>|null>  $rules
     */
    public function handle(Identity $actor, array $rules): void
    {
        if (! $this->scopes->get($actor, 'settings.manage')->allows()) {
            throw HouseException::notAllowed();
        }

        $known = SchoolClass::query()->pluck('ulid')->all();

        foreach ($rules as $classes) {
            if ($classes !== null && ($classes === [] || array_diff($classes, $known) !== [])) {
                throw HouseException::invalidDocumentRules();
            }
        }

        $this->transaction(function () use ($rules) {
            $before = SchoolSetting::query()->where('key', SchoolSettingsQuery::ADMISSION_DOCUMENT_RULES)->value('value');

            SchoolSetting::query()->updateOrCreate(
                ['key' => SchoolSettingsQuery::ADMISSION_DOCUMENT_RULES],
                ['value' => $rules],
            );

            $this->audit(new AuditEntry('settings.admission_documents_updated', 'school_settings', null, ['rules' => $before], ['rules' => $rules]));
        });
    }
}
