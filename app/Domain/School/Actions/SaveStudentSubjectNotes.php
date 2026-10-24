<?php

namespace App\Domain\School\Actions;

use App\Domain\Access\Queries\StaffRefQuery;
use App\Domain\Attendance\Queries\RosterQuery;
use App\Domain\Audit\Data\AuditEntry;
use App\Domain\Identity\Models\Identity;
use App\Domain\School\Exceptions\SyllabusException;
use App\Domain\School\Models\StudentSubjectNote;
use App\Domain\School\SyllabusAccess;
use App\Domain\Shared\Action;
use App\Domain\Shared\CalendarDate;

class SaveStudentSubjectNotes extends Action
{
    public const MAX_NOTE_LENGTH = 240;

    public function __construct(
        private readonly SyllabusAccess $access,
        private readonly StaffRefQuery $staff,
        private readonly RosterQuery $roster,
    ) {}

    /**
     * Saves private notes for the parents of individual students, under one
     * subject. `$notes` maps a student's ulid to the text; an empty text
     * removes that student's note. Only students on the section's roster today
     * may be named. Returns how many notes changed.
     *
     * @param  array<string, string>  $notes
     */
    public function handle(Identity $actor, string $sectionUlid, string $subjectUlid, array $notes): int
    {
        $pair = $this->access->pairOrFail($actor, $sectionUlid, $subjectUlid);

        $keys = [];

        foreach ($this->roster->for($pair->section->sectionKey, CalendarDate::today()) as $row) {
            $keys[$row->ulid] = $row->studentKey;
        }

        foreach ($notes as $studentUlid => $text) {
            if (! isset($keys[$studentUlid])) {
                throw SyllabusException::notFound();
            }

            if (mb_strlen(trim($text)) > self::MAX_NOTE_LENGTH) {
                throw SyllabusException::noteTooLong(self::MAX_NOTE_LENGTH);
            }
        }

        $actorStaff = $this->staff->forIdentity($actor);

        return $this->transaction(function () use ($pair, $notes, $keys, $actorStaff) {
            $existing = StudentSubjectNote::query()
                ->where('academic_year_id', $pair->section->academicYearKey)
                ->where('subject_id', $pair->subjectKey)
                ->whereIn('student_id', array_values($keys))
                ->pluck('note', 'student_id')
                ->all();

            $changed = 0;

            foreach ($notes as $studentUlid => $text) {
                $text = trim($text);
                $studentKey = $keys[$studentUlid];
                $old = $existing[$studentKey] ?? '';

                if ($old === $text) {
                    continue;
                }

                $changed++;
                $where = ['academic_year_id' => $pair->section->academicYearKey, 'subject_id' => $pair->subjectKey, 'student_id' => $studentKey];

                if ($text === '') {
                    StudentSubjectNote::query()->where($where)->delete();
                } else {
                    StudentSubjectNote::query()->updateOrCreate($where, ['note' => $text, 'updated_by_staff_id' => $actorStaff?->staffKey]);
                }
            }

            $this->audit(new AuditEntry('subjects.student_notes_saved', 'section', $pair->section->ulid, null, [
                'subject' => $pair->subjectUlid,
                'notes_changed' => $changed,
            ]));

            return $changed;
        });
    }
}
