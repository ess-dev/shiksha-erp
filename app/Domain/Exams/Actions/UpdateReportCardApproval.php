<?php

namespace App\Domain\Exams\Actions;

use App\Domain\Access\Queries\PermissionScopesQuery;
use App\Domain\Audit\Data\AuditEntry;
use App\Domain\Exams\Exceptions\GradeScaleException;
use App\Domain\Identity\Models\Identity;
use App\Domain\School\Queries\SchoolSettingsQuery;
use App\Domain\Shared\Action;
use Illuminate\Support\Facades\DB;

class UpdateReportCardApproval extends Action
{
    public function __construct(
        private readonly PermissionScopesQuery $scopes,
        private readonly SchoolSettingsQuery $settings,
    ) {}

    /**
     * Turns "Report cards need approval" on or off (needs `settings.manage`).
     * Report cards already waiting stay waiting.
     */
    public function handle(Identity $actor, bool $required): void
    {
        if (! $this->scopes->get($actor, 'settings.manage')->allows()) {
            throw GradeScaleException::notAllowed();
        }

        $this->transaction(function () use ($required) {
            $before = $this->settings->reportCardApproval();
            // The setting row belongs to School's settings table; Exams owns this key.
            $settings = DB::connection(config('multitenancy.tenant_database_connection_name'))->table('school_settings');
            $value = json_encode($required, JSON_THROW_ON_ERROR);

            if ((clone $settings)->where('key', SchoolSettingsQuery::REPORT_CARD_APPROVAL)->exists()) {
                $settings->where('key', SchoolSettingsQuery::REPORT_CARD_APPROVAL)->update(['value' => $value, 'updated_at' => now()]);
            } else {
                $settings->insert(['key' => SchoolSettingsQuery::REPORT_CARD_APPROVAL, 'value' => $value, 'created_at' => now(), 'updated_at' => now()]);
            }

            $this->audit(new AuditEntry('settings.report_card_approval_updated', 'school_settings', null, ['required' => $before], ['required' => $required]));
        });
    }
}
