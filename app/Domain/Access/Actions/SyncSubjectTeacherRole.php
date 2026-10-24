<?php

namespace App\Domain\Access\Actions;

use App\Domain\Access\Authorizer;
use App\Domain\Access\Data\Scope;
use App\Domain\Access\Enums\ScopeType;
use App\Domain\Access\Models\Role;
use App\Domain\Access\Models\RoleAssignment;
use App\Domain\Access\Models\Staff;
use App\Domain\Access\RoleBuilder;
use App\Domain\Audit\Data\AuditEntry;
use App\Domain\Shared\Action;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Keeps the automatic "Subject teacher" assignment of one staff member in
 * step with the subject teachers chosen for the current year's sections: one
 * assignment (source `auto`) scoped to every section where they were chosen
 * for at least one subject, removed when they are chosen nowhere. It is the
 * system acting on a choice that was already authorised (SetSectionSubjectTeacher
 * checked the actor), so it does not check the grant guardrails.
 *
 * What the role allows inside a section is narrowed again by the Subjects
 * screen: a subject teacher edits only the subjects they were chosen for.
 */
class SyncSubjectTeacherRole extends Action
{
    public const TEMPLATE_KEY = 'subject_teacher';

    public function __construct(
        private readonly RoleBuilder $builder,
        private readonly Authorizer $authorizer,
    ) {}

    public function handle(string $staffUlid): void
    {
        $this->transaction(function () use ($staffUlid) {
            $staff = Staff::query()->where('ulid', $staffUlid)->firstOrFail();
            $role = Role::query()->where('source_template_key', self::TEMPLATE_KEY)->orderBy('id')->first()
                ?? $this->builder->fromTemplate(self::TEMPLATE_KEY)['role'];

            $sections = $this->sectionsTaught($staff->id);
            $assignment = RoleAssignment::query()
                ->where('role_id', $role->id)
                ->where('staff_id', $staff->id)
                ->where('source', 'auto')
                ->where('scope_type', ScopeType::Sections->value)
                ->first();

            $before = $assignment?->toScope()->sectionUlids() ?? [];

            if ($sections === []) {
                $assignment?->delete();
            } elseif ($assignment === null) {
                RoleAssignment::query()->create([
                    'ulid' => strtolower((string) Str::ulid()),
                    'role_id' => $role->id,
                    'staff_id' => $staff->id,
                    'scope_type' => ScopeType::Sections,
                    'scope' => Scope::sections($sections)->toStored(),
                    'source' => 'auto',
                ]);
            } else {
                $assignment->update(['scope' => Scope::sections($sections)->toStored()]);
            }

            $this->audit(new AuditEntry('roles.subject_teacher_synced', 'staff', $staffUlid, ['sections' => $before], [
                'sections' => $sections,
                'role' => $role->ulid,
            ]));

            $this->authorizer->flush();
        });
    }

    /**
     * @return list<string>
     */
    private function sectionsTaught(int $staffKey): array
    {
        $ulids = DB::connection(config('multitenancy.tenant_database_connection_name'))->table('section_subject_teachers as t')
            ->join('sections', 'sections.id', '=', 't.section_id')
            ->join('academic_years', 'academic_years.id', '=', 'sections.academic_year_id')
            ->where('t.staff_id', $staffKey)
            ->where('academic_years.is_current', true)
            ->where('sections.status', 'active')
            ->distinct()
            ->orderBy('sections.ulid')
            ->pluck('sections.ulid')
            ->all();

        return array_values(array_map(fn ($ulid) => (string) $ulid, $ulids));
    }
}
