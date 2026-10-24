<?php

namespace App\Domain\Exams\Actions;

use App\Domain\Access\Queries\PermissionScopesQuery;
use App\Domain\Audit\Data\AuditEntry;
use App\Domain\Exams\Exceptions\GradeScaleException;
use App\Domain\Exams\GradeScale;
use App\Domain\Exams\Queries\GradeScaleQuery;
use App\Domain\Identity\Models\Identity;
use App\Domain\School\Queries\SchoolSettingsQuery;
use App\Domain\Shared\Action;
use Illuminate\Support\Facades\DB;

class UpdateGradeScale extends Action
{
    public function __construct(
        private readonly PermissionScopesQuery $scopes,
        private readonly GradeScaleQuery $scales,
    ) {}

    /**
     * Saves the school's grading scale (needs `settings.manage`). It applies to
     * exams that have no published marks yet; exams with published marks keep
     * the scale they were published with.
     *
     * @param  array<mixed>  $rows  list of {grade, min_percent, grade_point}, highest first
     */
    public function handle(Identity $actor, array $rows): GradeScale
    {
        if (! $this->scopes->get($actor, 'settings.manage')->allows()) {
            throw GradeScaleException::notAllowed();
        }

        $scale = GradeScale::fromRows($rows);

        $this->transaction(function () use ($scale) {
            $before = $this->scales->current()->toRows();

            // The setting row belongs to School's settings table; Exams owns this key and its rules.
            $settings = DB::connection(config('multitenancy.tenant_database_connection_name'))->table('school_settings');
            $value = json_encode($scale->toRows(), JSON_THROW_ON_ERROR);

            if ((clone $settings)->where('key', SchoolSettingsQuery::GRADE_SCALE)->exists()) {
                $settings->where('key', SchoolSettingsQuery::GRADE_SCALE)->update(['value' => $value, 'updated_at' => now()]);
            } else {
                $settings->insert(['key' => SchoolSettingsQuery::GRADE_SCALE, 'value' => $value, 'created_at' => now(), 'updated_at' => now()]);
            }

            $this->audit(new AuditEntry('settings.grade_scale_updated', 'school_settings', null, ['grades' => $before], ['grades' => $scale->toRows()]));
        });

        return $scale;
    }
}
