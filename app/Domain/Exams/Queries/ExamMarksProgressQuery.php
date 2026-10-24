<?php

namespace App\Domain\Exams\Queries;

use App\Domain\Exams\Data\ExamMarksProgressData;
use App\Domain\Exams\Enums\ExamStatus;
use App\Domain\Exams\Models\Exam;
use Illuminate\Support\Facades\DB;

/**
 * Marks progress for the Exams list, for exams with marking open or results
 * published: every active section of the exam's year that it applies to,
 * times the examined subjects of that section's class.
 */
class ExamMarksProgressQuery
{
    /**
     * @param  iterable<Exam>  $exams
     * @param  array<int, list<int>>  $classIdsByExam  The classes each exam applies to
     * @param  array<int, list<int>>  $campusIdsByExam  The campuses each exam applies to
     * @return array<int, ExamMarksProgressData> by exam id
     */
    public function forExams(iterable $exams, array $classIdsByExam, array $campusIdsByExam): array
    {
        $exams = array_values(array_filter(
            is_array($exams) ? $exams : iterator_to_array($exams),
            fn (Exam $exam) => $exam->status === ExamStatus::Marking || $exam->status === ExamStatus::Published,
        ));

        if ($exams === []) {
            return [];
        }

        $connection = DB::connection(config('multitenancy.tenant_database_connection_name'));
        $examIds = array_map(fn (Exam $exam) => $exam->id, $exams);
        $yearIds = array_values(array_unique(array_map(fn (Exam $exam) => $exam->academic_year_id, $exams)));

        $sections = $connection->table('sections')->whereIn('academic_year_id', $yearIds)->where('status', 'active')
            ->get(['id', 'academic_year_id', 'school_class_id', 'campus_id']);

        $enrolled = $connection->table('enrollments')->where('is_current', true)->whereNull('ended_on')
            ->groupBy('section_id')->selectRaw('section_id, count(*) as total')->pluck('total', 'section_id')->all();

        $subjectsByClass = [];

        foreach ($connection->table('school_class_subject as link')->join('subjects', 'subjects.id', '=', 'link.subject_id')
            ->where('subjects.status', 'active')->where('subjects.type', '!=', 'co_scholastic')
            ->get(['link.school_class_id', 'subjects.id']) as $row) {
            $subjectsByClass[(int) $row->school_class_id][] = (int) $row->id;
        }

        $marked = [];

        foreach ($connection->table('exam_marks')->whereIn('exam_id', $examIds)->groupBy('exam_id', 'section_id', 'subject_id')
            ->selectRaw('exam_id, section_id, subject_id, count(*) as total')->get() as $row) {
            $marked[$row->exam_id.'|'.$row->section_id.'|'.$row->subject_id] = (int) $row->total;
        }

        $published = [];

        foreach ($connection->table('exam_mark_sheets')->whereIn('exam_id', $examIds)->whereNotNull('published_at')
            ->get(['exam_id', 'section_id', 'subject_id']) as $row) {
            $published[$row->exam_id.'|'.$row->section_id.'|'.$row->subject_id] = true;
        }

        $progress = [];

        foreach ($exams as $exam) {
            $sheets = $entered = $shown = 0;

            foreach ($sections as $section) {
                if ((int) $section->academic_year_id !== $exam->academic_year_id
                    || ! in_array((int) $section->school_class_id, $classIdsByExam[$exam->id] ?? [], true)
                    || ! in_array((int) $section->campus_id, $campusIdsByExam[$exam->id] ?? [], true)) {
                    continue;
                }

                $students = (int) ($enrolled[$section->id] ?? 0);

                foreach ($subjectsByClass[(int) $section->school_class_id] ?? [] as $subjectId) {
                    $key = $exam->id.'|'.$section->id.'|'.$subjectId;
                    $sheets++;

                    if ($students > 0 && ($marked[$key] ?? 0) >= $students) {
                        $entered++;
                    }

                    if ($exam->status === ExamStatus::Published || isset($published[$key])) {
                        $shown++;
                    }
                }
            }

            $progress[$exam->id] = new ExamMarksProgressData($sheets, $entered, $shown);
        }

        return $progress;
    }

    /**
     * Ids of the exams (among these) that have any marks.
     *
     * @param  list<int>  $examIds
     * @return array<int, true>
     */
    public function withMarks(array $examIds): array
    {
        return array_fill_keys(array_map(intval(...), DB::connection(config('multitenancy.tenant_database_connection_name'))
            ->table('exam_marks')->whereIn('exam_id', $examIds)->distinct()->pluck('exam_id')->all()), true);
    }
}
