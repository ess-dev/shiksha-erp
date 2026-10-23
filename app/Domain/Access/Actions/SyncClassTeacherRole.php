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
use Illuminate\Support\Str;

/**
 * Keeps the automatic "Class teacher" assignments in step with the class
 * teacher of each section: one assignment per teacher (source `auto`), scoped
 * to the sections they are class teacher of. It is the system acting on a
 * structure change that was already authorised, so it does not check the
 * grant guardrails; callers (SetClassTeacher) have checked the actor.
 */
class SyncClassTeacherRole extends Action
{
    public const TEMPLATE_KEY = 'class_teacher';

    public function __construct(
        private readonly RoleBuilder $builder,
        private readonly Authorizer $authorizer,
    ) {}

    /**
     * Moves a section from one class teacher to another (either may be null).
     */
    public function handle(string $sectionUlid, ?string $previousStaffUlid, ?string $newStaffUlid): void
    {
        $this->transaction(function () use ($sectionUlid, $previousStaffUlid, $newStaffUlid) {
            $role = Role::query()->where('source_template_key', self::TEMPLATE_KEY)->orderBy('id')->first()
                ?? $this->builder->fromTemplate(self::TEMPLATE_KEY)['role'];

            if ($previousStaffUlid !== null && $previousStaffUlid !== $newStaffUlid) {
                $this->removeSection($role, $previousStaffUlid, $sectionUlid);
            }

            if ($newStaffUlid !== null) {
                $this->addSection($role, $newStaffUlid, $sectionUlid);
            }

            $this->audit(new AuditEntry('roles.class_teacher_synced', 'section', $sectionUlid, [
                'class_teacher' => $previousStaffUlid,
            ], [
                'class_teacher' => $newStaffUlid,
                'role' => $role->ulid,
            ]));

            $this->authorizer->flush();
        });
    }

    private function removeSection(Role $role, string $staffUlid, string $sectionUlid): void
    {
        $assignment = $this->autoAssignment($role, $staffUlid);

        if ($assignment === null) {
            return;
        }

        $remaining = array_values(array_diff($assignment->toScope()->sectionUlids(), [$sectionUlid]));

        if ($remaining === []) {
            $assignment->delete();

            return;
        }

        $assignment->update(['scope' => Scope::sections($remaining)->toStored()]);
    }

    private function addSection(Role $role, string $staffUlid, string $sectionUlid): void
    {
        $assignment = $this->autoAssignment($role, $staffUlid);

        if ($assignment !== null) {
            $assignment->update(['scope' => Scope::sections([...$assignment->toScope()->sectionUlids(), $sectionUlid])->toStored()]);

            return;
        }

        $staff = Staff::query()->where('ulid', $staffUlid)->firstOrFail();

        RoleAssignment::query()->create([
            'ulid' => strtolower((string) Str::ulid()),
            'role_id' => $role->id,
            'staff_id' => $staff->id,
            'scope_type' => ScopeType::Sections,
            'scope' => Scope::sections([$sectionUlid])->toStored(),
            'source' => 'auto',
        ]);
    }

    private function autoAssignment(Role $role, string $staffUlid): ?RoleAssignment
    {
        return RoleAssignment::query()
            ->where('role_id', $role->id)
            ->where('source', 'auto')
            ->where('scope_type', ScopeType::Sections->value)
            ->whereHas('staff', fn ($query) => $query->where('ulid', $staffUlid))
            ->first();
    }
}
