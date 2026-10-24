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
use Illuminate\Support\Facades\DB;

class SaveClassSyllabus extends Action
{
    public const MAX_CHAPTERS = 60;

    public const MAX_CHAPTER_LENGTH = 120;

    public function __construct(
        private readonly SyllabusAccess $access,
        private readonly StaffRefQuery $staff,
    ) {}

    /**
     * Saves the textbook, chapter list and assessment of a subject for the
     * whole class (every section, every campus) in the current academic year.
     * `$chapterLines` is the text box: one chapter per line, blank lines
     * dropped. A section's ticks survive only for chapters that kept their
     * position and name, so reordering starts that subject's progress again.
     */
    public function handle(Identity $actor, string $sectionUlid, string $subjectUlid, ?string $book, string $chapterLines, ?string $assessment): void
    {
        $pairs = $this->access->pairs($actor);
        $pair = $this->access->pairOrFail($actor, $sectionUlid, $subjectUlid);

        if (! $this->access->mayEditClass($actor, $pair, $pairs)) {
            throw SyllabusException::notAllowed();
        }

        $chapters = $this->chapters($chapterLines);
        $class = SchoolClass::query()->where('ulid', $pair->section->classUlid)->firstOrFail();
        $book = $this->blankToNull($book);
        $assessment = $this->blankToNull($assessment);
        $actorStaff = $this->staff->forIdentity($actor);

        $this->transaction(function () use ($pair, $class, $chapters, $book, $assessment, $actorStaff) {
            $existing = SubjectSyllabus::query()
                ->where('academic_year_id', $pair->section->academicYearKey)
                ->where('school_class_id', $class->id)
                ->where('subject_id', $pair->subjectKey)
                ->first();

            $before = $existing->chapters ?? [];

            SubjectSyllabus::query()->updateOrCreate([
                'academic_year_id' => $pair->section->academicYearKey,
                'school_class_id' => $class->id,
                'subject_id' => $pair->subjectKey,
            ], [
                'book' => $book,
                'chapters' => $chapters,
                'assessment' => $assessment,
                'updated_by_staff_id' => $actorStaff?->staffKey,
            ]);

            $reset = $before === $chapters ? 0 : $this->keepMatchingTicks($pair->section->academicYearKey, $class->id, $pair->subjectKey, $before, $chapters);

            $this->audit(new AuditEntry('subjects.syllabus_saved', 'school_class', $class->ulid, [
                'subject' => $pair->subjectUlid,
                'chapters' => count($before),
            ], [
                'subject' => $pair->subjectUlid,
                'chapters' => count($chapters),
                'sections_progress_changed' => $reset,
            ]));
        });
    }

    /**
     * @return list<string>
     */
    private function chapters(string $lines): array
    {
        $chapters = [];

        foreach (preg_split('/\R/u', $lines) ?: [] as $line) {
            $name = trim($line);

            if ($name === '') {
                continue;
            }

            if (mb_strlen($name) > self::MAX_CHAPTER_LENGTH) {
                throw SyllabusException::chapterTooLong(self::MAX_CHAPTER_LENGTH);
            }

            $chapters[] = $name;
        }

        if ($chapters === []) {
            throw SyllabusException::needsChapter();
        }

        if (count($chapters) > self::MAX_CHAPTERS) {
            throw SyllabusException::tooManyChapters(self::MAX_CHAPTERS);
        }

        return $chapters;
    }

    /**
     * Drops the ticks and "Now" marker of chapters whose position or name
     * changed, in every section of the class this year.
     *
     * @param  list<string>  $before
     * @param  list<string>  $after
     */
    private function keepMatchingTicks(int $yearKey, int $classKey, int $subjectKey, array $before, array $after): int
    {
        $same = fn (int $index): bool => isset($before[$index], $after[$index]) && $before[$index] === $after[$index];
        $changed = 0;

        $rows = SectionSubjectProgress::query()
            ->where('subject_id', $subjectKey)
            ->whereIn('section_id', DB::connection(config('multitenancy.tenant_database_connection_name'))->table('sections')
                ->where('academic_year_id', $yearKey)->where('school_class_id', $classKey)->select('id'))
            ->get();

        foreach ($rows as $row) {
            $done = array_values(array_filter($row->done_chapters, $same));
            $current = $row->current_chapter !== null && $same($row->current_chapter) ? $row->current_chapter : null;

            if ($done !== $row->done_chapters || $current !== $row->current_chapter) {
                $row->forceFill(['done_chapters' => $done, 'current_chapter' => $current])->save();
                $changed++;
            }
        }

        return $changed;
    }

    private function blankToNull(?string $value): ?string
    {
        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }
}
