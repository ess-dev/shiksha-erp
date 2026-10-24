<?php

namespace App\Domain\School\Queries;

use App\Domain\School\Data\ParentChapterData;
use App\Domain\School\Data\ParentSubjectData;
use App\Domain\School\Enums\ChapterState;
use Illuminate\Support\Facades\DB;

/**
 * What a student's parents read under "Subjects" for one academic year: the
 * subjects of the student's class with the book, teacher, chapter progress of
 * the student's section and the teacher's notes. The caller has already
 * checked that the student belongs to the guardian and that the section is
 * the one the student was enrolled in that year.
 *
 * The subject list is the class's list today (the school keeps no history of
 * it). For a past year only subjects that had a chapter list or progress saved
 * that year are shown.
 */
class StudentSubjectsQuery
{
    /**
     * @return list<ParentSubjectData>
     */
    public function get(int $studentKey, int $sectionKey, int $yearKey, bool $isCurrentYear, ?string $classTeacherName): array
    {
        $db = DB::connection(config('multitenancy.tenant_database_connection_name'));

        $classId = $db->table('sections')->where('id', $sectionKey)->value('school_class_id');

        if ($classId === null) {
            return [];
        }

        $subjects = $db->table('school_class_subject as link')
            ->join('subjects', 'subjects.id', '=', 'link.subject_id')
            ->where('link.school_class_id', $classId)
            ->where('subjects.status', 'active')
            ->orderBy('subjects.name')
            ->get(['subjects.id', 'subjects.name']);

        $syllabi = $db->table('subject_syllabi')
            ->where('academic_year_id', $yearKey)
            ->where('school_class_id', $classId)
            ->get()
            ->keyBy('subject_id');

        $progress = $db->table('section_subject_progress')->where('section_id', $sectionKey)->get()->keyBy('subject_id');
        $teachers = $db->table('section_subject_teachers')
            ->join('staff', 'staff.id', '=', 'section_subject_teachers.staff_id')
            ->where('section_subject_teachers.section_id', $sectionKey)
            ->pluck('staff.full_name', 'section_subject_teachers.subject_id');
        $notes = $db->table('student_subject_notes')
            ->where('academic_year_id', $yearKey)
            ->where('student_id', $studentKey)
            ->pluck('note', 'subject_id');

        $rows = [];

        foreach ($subjects as $subject) {
            $syllabus = $syllabi->get($subject->id);
            $mine = $progress->get($subject->id);
            $note = $notes->get($subject->id);

            if (! $isCurrentYear && $syllabus === null && $mine === null && $note === null) {
                continue;
            }

            $titles = $syllabus === null ? [] : $this->list($syllabus->chapters);
            $done = $mine === null ? [] : array_map('intval', $this->list($mine->done_chapters));
            $current = $mine?->current_chapter === null ? null : (int) $mine->current_chapter;

            $chapters = [];

            foreach ($titles as $index => $title) {
                $state = in_array($index, $done, true) ? ChapterState::Done : ($current === $index ? ChapterState::Current : ChapterState::Upcoming);
                $chapters[] = new ParentChapterData($index + 1, (string) $title, $state);
            }

            $doneCount = count(array_filter($chapters, fn (ParentChapterData $chapter) => $chapter->state === ChapterState::Done));

            $rows[] = new ParentSubjectData(
                name: (string) $subject->name,
                book: $syllabus?->book,
                teacher_name: $teachers->get($subject->id) ?? $classTeacherName,
                periods_per_week: $mine?->periods_per_week === null ? null : (int) $mine->periods_per_week,
                percent_done: $chapters === [] ? null : intdiv($doneCount * 200 + count($chapters), 2 * count($chapters)),
                current_chapter: $current === null ? null : ($titles[$current] ?? null),
                teacher_note: $this->blankToNull($mine?->note),
                student_note: $this->blankToNull($note),
                assessment: $syllabus?->assessment,
                chapters: $chapters,
            );
        }

        return $rows;
    }

    /**
     * @return list<mixed>
     */
    private function list(mixed $json): array
    {
        $decoded = is_string($json) ? json_decode($json, true) : $json;

        return is_array($decoded) ? array_values($decoded) : [];
    }

    private function blankToNull(mixed $value): ?string
    {
        return is_string($value) && trim($value) !== '' ? $value : null;
    }
}
