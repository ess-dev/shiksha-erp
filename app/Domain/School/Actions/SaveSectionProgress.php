<?php

namespace App\Domain\School\Actions;

use App\Domain\Access\Queries\StaffRefQuery;
use App\Domain\Audit\Data\AuditEntry;
use App\Domain\Identity\Models\Identity;
use App\Domain\School\Exceptions\SyllabusException;
use App\Domain\School\Models\SchoolClass;
use App\Domain\School\Models\SectionSubjectProgress;
use App\Domain\School\Models\SubjectSyllabus;
use App\Domain\School\SyllabusAccess;
use App\Domain\Shared\Action;

class SaveSectionProgress extends Action
{
    public function __construct(
        private readonly SyllabusAccess $access,
        private readonly StaffRefQuery $staff,
    ) {}

    /**
     * Saves one section's progress in a subject: which chapters are finished,
     * which one is "Now", periods a week and a note for the section's
     * parents. Chapter positions count from 0.
     *
     * @param  list<int>  $done
     */
    public function handle(Identity $actor, string $sectionUlid, string $subjectUlid, array $done, ?int $current, ?int $periods, ?string $note): void
    {
        $pair = $this->access->pairOrFail($actor, $sectionUlid, $subjectUlid);

        $class = SchoolClass::query()->where('ulid', $pair->section->classUlid)->firstOrFail();
        $chapterCount = count(SubjectSyllabus::query()
            ->where('academic_year_id', $pair->section->academicYearKey)
            ->where('school_class_id', $class->id)
            ->where('subject_id', $pair->subjectKey)
            ->value('chapters') ?? []);

        $done = array_values(array_unique($done));
        sort($done);

        if (($done !== [] || $current !== null) && $chapterCount === 0) {
            throw SyllabusException::noChaptersYet();
        }

        foreach ([...$done, ...($current === null ? [] : [$current])] as $index) {
            if ($index < 0 || $index >= $chapterCount) {
                throw SyllabusException::unknownChapter();
            }
        }

        $note = trim((string) $note);
        $actorStaff = $this->staff->forIdentity($actor);

        $this->transaction(function () use ($pair, $done, $current, $periods, $note, $actorStaff) {
            $existing = SectionSubjectProgress::query()
                ->where('section_id', $pair->section->sectionKey)
                ->where('subject_id', $pair->subjectKey)
                ->first();

            SectionSubjectProgress::query()->updateOrCreate([
                'section_id' => $pair->section->sectionKey,
                'subject_id' => $pair->subjectKey,
            ], [
                'done_chapters' => $done,
                'current_chapter' => $current,
                'periods_per_week' => $periods,
                'note' => $note === '' ? null : $note,
                'updated_by_staff_id' => $actorStaff?->staffKey,
            ]);

            $this->audit(new AuditEntry('subjects.progress_saved', 'section', $pair->section->ulid, [
                'subject' => $pair->subjectUlid,
                'chapters_done' => count($existing->done_chapters ?? []),
                'current' => $existing?->current_chapter,
            ], [
                'subject' => $pair->subjectUlid,
                'chapters_done' => count($done),
                'current' => $current,
                'periods' => $periods,
                'has_note' => $note !== '',
            ]));
        });
    }
}
