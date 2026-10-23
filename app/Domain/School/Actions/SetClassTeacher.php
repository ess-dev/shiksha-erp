<?php

namespace App\Domain\School\Actions;

use App\Domain\Access\Actions\SyncClassTeacherRole;
use App\Domain\Access\Queries\StaffRefQuery;
use App\Domain\Audit\Data\AuditEntry;
use App\Domain\Identity\Models\Identity;
use App\Domain\School\Exceptions\CalendarException;
use App\Domain\School\Models\Section;
use App\Domain\School\Queries\SectionDirectoryQuery;
use App\Domain\School\StructureGuard;
use App\Domain\Shared\Action;

class SetClassTeacher extends Action
{
    public function __construct(
        private readonly StructureGuard $guard,
        private readonly StaffRefQuery $staff,
        private readonly SectionDirectoryQuery $sections,
        private readonly SyncClassTeacherRole $syncRole,
    ) {}

    /**
     * Names the class teacher of a section (null clears it). The teacher gets
     * the "Class teacher" role over that section automatically and loses it
     * when replaced. Needs `structure.manage` over where the section sits.
     */
    public function handle(Identity $actor, Section $section, ?string $staffUlid): Section
    {
        $this->guard->assertMayManageAt($actor, $section->scopeTarget());

        if ($section->status !== 'active') {
            throw CalendarException::sectionInactive();
        }

        $newStaff = null;

        if ($staffUlid !== null) {
            $newStaff = $this->staff->find($staffUlid);

            if ($newStaff === null || ! $newStaff->isActive) {
                throw CalendarException::staffUnavailable();
            }
        }

        $previousUlid = $this->sections->find($section->ulid)?->classTeacherUlid;

        if ($previousUlid === $staffUlid) {
            return $section;
        }

        return $this->transaction(function () use ($section, $newStaff, $previousUlid, $staffUlid) {
            $section->forceFill(['class_teacher_staff_id' => $newStaff?->staffKey])->save();

            $this->syncRole->handle($section->ulid, $previousUlid, $staffUlid);

            $this->audit(new AuditEntry('structure.class_teacher_set', 'section', $section->ulid, ['class_teacher' => $previousUlid], ['class_teacher' => $staffUlid]));

            return $section;
        });
    }
}
