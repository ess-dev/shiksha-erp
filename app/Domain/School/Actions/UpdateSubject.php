<?php

namespace App\Domain\School\Actions;

use App\Domain\Audit\Data\AuditEntry;
use App\Domain\Identity\Models\Identity;
use App\Domain\School\Enums\SubjectType;
use App\Domain\School\Exceptions\StructureException;
use App\Domain\School\Models\Subject;
use App\Domain\School\SubjectGuard;
use App\Domain\Shared\Action;
use Illuminate\Support\Facades\DB;

class UpdateSubject extends Action
{
    public function __construct(private readonly SubjectGuard $guard) {}

    /**
     * Renames a subject or changes its code or type. Everything that refers to
     * the subject follows, because it is linked by id, not by name.
     */
    public function handle(Identity $actor, Subject $subject, string $name, string $code, SubjectType $type): Subject
    {
        $this->guard->assertMayChangeSubject($actor, $subject);

        $name = trim($name);
        $code = strtoupper(trim($code));

        return $this->transaction(function () use ($subject, $name, $code, $type) {
            $duplicate = Subject::query()
                ->whereKeyNot($subject->id)
                ->where(fn ($query) => $query->whereRaw('lower(name) = ?', [mb_strtolower($name)])->orWhere('code', $code))
                ->exists();

            if ($duplicate) {
                throw StructureException::duplicate("A subject named [{$name}] or with code [{$code}]");
            }

            if (! $type->isExamined() && $this->leavesAClassWithoutExaminedSubject($subject)) {
                throw StructureException::wouldLeaveNoExaminedSubject();
            }

            $before = ['name' => $subject->name, 'code' => $subject->code, 'type' => $subject->type->value];
            $after = ['name' => $name, 'code' => $code, 'type' => $type->value];

            if ($before !== $after) {
                $subject->forceFill(['name' => $name, 'code' => $code, 'type' => $type])->save();
            }

            $this->audit(new AuditEntry('subjects.subject_updated', 'subject', $subject->ulid, $before, $after));

            return $subject;
        });
    }

    /**
     * Whether some class teaching this subject has no other examined subject.
     */
    private function leavesAClassWithoutExaminedSubject(Subject $subject): bool
    {
        foreach ($this->guard->classIdsTeaching($subject->id) as $classId) {
            $others = DB::connection('tenant')->table('school_class_subject')
                ->join('subjects', 'subjects.id', '=', 'school_class_subject.subject_id')
                ->where('school_class_subject.school_class_id', $classId)
                ->where('subjects.id', '!=', $subject->id)
                ->where('subjects.type', '!=', SubjectType::CoScholastic->value)
                ->exists();

            if (! $others) {
                return true;
            }
        }

        return false;
    }
}
