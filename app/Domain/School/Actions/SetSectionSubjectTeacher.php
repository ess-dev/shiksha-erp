<?php

namespace App\Domain\School\Actions;

use App\Domain\Access\Queries\StaffRefQuery;
use App\Domain\Audit\Data\AuditEntry;
use App\Domain\Identity\Models\Identity;
use App\Domain\School\Exceptions\StructureException;
use App\Domain\School\Models\Section;
use App\Domain\School\Models\SectionSubjectTeacher;
use App\Domain\School\Models\Subject;
use App\Domain\School\SubjectGuard;
use App\Domain\Shared\Action;
use Illuminate\Support\Facades\DB;

class SetSectionSubjectTeacher extends Action
{
    public function __construct(
        private readonly SubjectGuard $guard,
        private readonly StaffRefQuery $staff,
    ) {}

    /**
     * Chooses who teaches a subject in a section (null goes back to
     * "Automatic": the section's class teacher). Needs `subjects.configure`
     * over where the section sits.
     */
    public function handle(Identity $actor, Section $section, Subject $subject, ?string $staffUlid): void
    {
        $this->guard->assertMayChooseTeachers($actor, $section);

        if ($section->status !== 'active') {
            throw StructureException::teacherUnavailable();
        }

        if (! in_array($section->school_class_id, $this->guard->classIdsTeaching($subject->id), true)) {
            throw StructureException::subjectNotTaught();
        }

        $person = null;

        if ($staffUlid !== null) {
            $person = $this->staff->find($staffUlid);

            if ($person === null || ! $person->isActive) {
                throw StructureException::teacherUnavailable();
            }
        }

        $current = SectionSubjectTeacher::query()
            ->where('section_id', $section->id)
            ->where('subject_id', $subject->id)
            ->first();

        $previousUlid = $current === null ? null : DB::connection('tenant')->table('staff')->where('id', $current->staff_id)->value('ulid');

        if ($previousUlid === $staffUlid) {
            return;
        }

        $this->transaction(function () use ($section, $subject, $person, $current, $previousUlid, $staffUlid) {
            if ($person === null) {
                $current?->delete();
            } elseif ($current === null) {
                SectionSubjectTeacher::query()->create(['section_id' => $section->id, 'subject_id' => $subject->id, 'staff_id' => $person->staffKey]);
            } else {
                $current->forceFill(['staff_id' => $person->staffKey])->save();
            }

            $this->audit(new AuditEntry('subjects.teacher_set', 'section', $section->ulid, [
                'subject' => $subject->code, 'teacher' => $previousUlid,
            ], [
                'subject' => $subject->code, 'teacher' => $staffUlid,
            ]));
        });
    }
}
