<?php

namespace App\Domain\School\Actions;

use App\Domain\Access\Data\ScopeTarget;
use App\Domain\Access\Queries\StaffRefQuery;
use App\Domain\Audit\Data\AuditEntry;
use App\Domain\Identity\Models\Identity;
use App\Domain\School\Exceptions\CalendarException;
use App\Domain\School\Exceptions\StructureException;
use App\Domain\School\Models\AcademicYear;
use App\Domain\School\Models\Campus;
use App\Domain\School\Models\SchoolClass;
use App\Domain\School\Models\Section;
use App\Domain\School\StructureGuard;
use App\Domain\Shared\Action;
use Illuminate\Support\Str;
use InvalidArgumentException;

class CreateSection extends Action
{
    public function __construct(
        private readonly StructureGuard $guard,
        private readonly StaffRefQuery $staff,
        private readonly SetClassTeacher $setClassTeacher,
    ) {}

    /**
     * Adds a section ("5 A") of a class at a campus for an academic year. The
     * actor needs `structure.manage` over that campus and class level. A room
     * and a class teacher may be given at once; a teacher can lead only one
     * section a year.
     */
    public function handle(Identity $actor, AcademicYear $year, Campus $campus, SchoolClass $class, string $name, ?int $capacity = null, ?string $room = null, ?string $classTeacherUlid = null): Section
    {
        $this->guard->assertMayManageAt($actor, new ScopeTarget($campus->ulid, $class->level));

        if ($capacity !== null && $capacity < 1) {
            throw new InvalidArgumentException('Capacity must be at least 1.');
        }

        $room = $room === null || trim($room) === '' ? null : trim($room);

        return $this->transaction(function () use ($actor, $year, $campus, $class, $name, $capacity, $room, $classTeacherUlid) {
            if (! $campus->isActive()) {
                throw StructureException::campusInactive();
            }

            $name = strtoupper(trim($name));

            $exists = Section::query()
                ->where('academic_year_id', $year->id)
                ->where('campus_id', $campus->id)
                ->where('school_class_id', $class->id)
                ->where('name', $name)
                ->exists();

            if ($exists) {
                throw StructureException::duplicate("Section [{$name}] of {$class->name} at {$campus->name} for {$year->name}");
            }

            $teacher = null;

            if ($classTeacherUlid !== null) {
                $teacher = $this->staff->find($classTeacherUlid);

                if ($teacher === null || ! $teacher->isActive) {
                    throw CalendarException::staffUnavailable();
                }

                $busy = Section::query()
                    ->where('academic_year_id', $year->id)
                    ->where('status', 'active')
                    ->where('class_teacher_staff_id', $teacher->staffKey)
                    ->exists();

                if ($busy) {
                    throw StructureException::teacherAlreadyLeads();
                }
            }

            $section = Section::query()->create([
                'ulid' => strtolower((string) Str::ulid()),
                'academic_year_id' => $year->id,
                'campus_id' => $campus->id,
                'school_class_id' => $class->id,
                'name' => $name,
                'capacity' => $capacity,
                'room' => $room,
                'status' => 'active',
            ]);

            $this->audit(new AuditEntry('structure.section_created', 'section', $section->ulid, null, [
                'academic_year' => $year->ulid,
                'campus' => $campus->ulid,
                'class' => $class->ulid,
                'name' => $name,
                'capacity' => $capacity,
                'room' => $room,
            ]));

            if ($teacher !== null) {
                $this->setClassTeacher->handle($actor, $section, $teacher->ulid);
            }

            return $section;
        });
    }
}
