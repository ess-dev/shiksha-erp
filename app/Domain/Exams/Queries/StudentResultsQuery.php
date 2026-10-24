<?php

namespace App\Domain\Exams\Queries;

use App\Domain\Exams\Data\StudentExamResultData;
use App\Domain\Exams\Data\StudentResultsData;
use App\Domain\Exams\Data\StudentSubjectResultData;
use App\Domain\Exams\Enums\ExamStatus;
use App\Domain\Exams\Enums\SubjectResultState;
use App\Domain\Exams\GradeScale;
use App\Domain\Exams\MarkValue;
use App\Domain\Exams\Models\Exam;
use App\Domain\Exams\Models\ExamMark;
use App\Domain\Exams\Percent;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

/**
 * A student's exam results in one academic session, from **published** marks
 * only: a subject's marks show once its teacher published them for the
 * section the marks were entered in, or once the whole exam's results were
 * published. Exams count when they are for the student's class at its campus
 * that session and marking has opened (scheduled and cancelled exams have no
 * results). Subjects are the class's examined subjects in the current session
 * (plus any with marks); in a past session only subjects with marks.
 */
class StudentResultsQuery
{
    public function __construct(private readonly GradeScaleQuery $scales) {}

    /**
     * @param  int  $sectionKey  The section the student was enrolled in that session
     */
    public function get(int $studentKey, int $sectionKey, int $yearKey, bool $isCurrentYear): StudentResultsData
    {
        return new StudentResultsData(
            exams: $this->exams($studentKey, $sectionKey, $yearKey, $isCurrentYear),
            scale: $this->scales->current()->data(),
        );
    }

    /**
     * One exam's result, or null when that exam is not among the student's results.
     */
    public function forExam(int $studentKey, int $sectionKey, int $yearKey, bool $isCurrentYear, string $examUlid): ?StudentExamResultData
    {
        foreach ($this->exams($studentKey, $sectionKey, $yearKey, $isCurrentYear) as $exam) {
            if ($exam->ulid === $examUlid) {
                return $exam;
            }
        }

        return null;
    }

    /**
     * @return list<StudentExamResultData>
     */
    private function exams(int $studentKey, int $sectionKey, int $yearKey, bool $isCurrentYear): array
    {
        $connection = DB::connection(config('multitenancy.tenant_database_connection_name'));
        $section = $connection->table('sections')->where('id', $sectionKey)->first(['school_class_id', 'campus_id']);

        if ($section === null) {
            return [];
        }

        $classKey = (int) $section->school_class_id;
        $campusKey = (int) $section->campus_id;

        $exams = Exam::query()
            ->where('academic_year_id', $yearKey)
            ->whereIn('status', [ExamStatus::Marking->value, ExamStatus::Published->value])
            ->where(fn ($query) => $query->where('applies_to_all_classes', true)
                ->orWhereExists(fn (Builder $pivot) => $pivot->selectRaw('1')->from('exam_school_class')
                    ->whereColumn('exam_school_class.exam_id', 'exams.id')->where('exam_school_class.school_class_id', $classKey)))
            ->where(fn ($query) => $query->where('applies_to_all_campuses', true)
                ->orWhereExists(fn (Builder $pivot) => $pivot->selectRaw('1')->from('campus_exam')
                    ->whereColumn('campus_exam.exam_id', 'exams.id')->where('campus_exam.campus_id', $campusKey)))
            ->orderBy('starts_on')
            ->orderBy('id')
            ->get();

        if ($exams->isEmpty()) {
            return [];
        }

        $examKeys = array_values(array_map(intval(...), $exams->pluck('id')->all()));

        /** @var array<int, array<int, ExamMark>> $marks exam => subject => mark */
        $marks = [];

        foreach (ExamMark::query()->where('student_id', $studentKey)->whereIn('exam_id', $examKeys)->get() as $mark) {
            $marks[$mark->exam_id][$mark->subject_id] = $mark;
        }

        $classSubjectKeys = $isCurrentYear
            ? array_values(array_map(intval(...), $connection->table('school_class_subject as link')
                ->join('subjects', 'subjects.id', '=', 'link.subject_id')
                ->where('link.school_class_id', $classKey)
                ->where('subjects.status', 'active')
                ->where('subjects.type', '!=', 'co_scholastic')
                ->pluck('subjects.id')
                ->all()))
            : [];

        $markedSubjectKeys = [];

        foreach ($marks as $bySubject) {
            $markedSubjectKeys = [...$markedSubjectKeys, ...array_keys($bySubject)];
        }

        /** @var array<int, string> $subjectNames */
        $subjectNames = $connection->table('subjects')
            ->whereIn('id', array_values(array_unique([...$classSubjectKeys, ...$markedSubjectKeys])))
            ->orderBy('name')
            ->pluck('name', 'id')
            ->all();

        $published = [];

        foreach ($connection->table('exam_mark_sheets')->whereIn('exam_id', $examKeys)->whereNotNull('published_at')->get(['exam_id', 'section_id', 'subject_id']) as $sheet) {
            $published[$sheet->exam_id.'|'.$sheet->section_id.'|'.$sheet->subject_id] = true;
        }

        $results = [];

        foreach ($exams as $exam) {
            $scale = $this->scales->forExam($exam);
            $examMarks = $marks[$exam->id] ?? [];
            $rows = [];
            $scored = 0;
            $counted = 0;
            $pending = 0;

            foreach ($subjectNames as $subjectKey => $subjectName) {
                $mark = $examMarks[$subjectKey] ?? null;

                if ($mark === null && ! in_array($subjectKey, $classSubjectKeys, true)) {
                    continue;
                }

                $visible = $exam->status === ExamStatus::Published
                    || isset($published[$exam->id.'|'.($mark->section_id ?? $sectionKey).'|'.$subjectKey]);

                if (! $visible) {
                    $pending++;
                    $rows[] = $this->row($subjectName, SubjectResultState::Awaiting);
                } elseif ($mark === null) {
                    $rows[] = $this->row($subjectName, SubjectResultState::NotEntered);
                } elseif ($mark->is_absent || $mark->half_marks === null) {
                    $rows[] = $this->row($subjectName, SubjectResultState::Absent);
                } else {
                    $scored += $mark->half_marks;
                    $counted++;
                    $rows[] = $this->published($subjectName, $mark->half_marks, 2 * $exam->max_marks, $scale);
                }
            }

            $outOf = 2 * $exam->max_marks * $counted;
            $overall = $outOf > 0 ? $scale->bandFor($scored, $outOf) : null;

            $results[] = new StudentExamResultData(
                ulid: $exam->ulid,
                name: $exam->name,
                held_on: $exam->starts_on,
                max_marks: $exam->max_marks,
                pending_count: $pending,
                subjects: $rows,
                total_marks: $outOf > 0 ? MarkValue::format($scored) : null,
                total_max: $outOf > 0 ? $exam->max_marks * $counted : null,
                percent_tenths: $outOf > 0 ? Percent::tenths($scored, $outOf) : null,
                grade: $overall?->grade,
                is_lowest_grade: $overall !== null && $scale->isLowest($overall),
            );
        }

        return $results;
    }

    private function row(string $subjectName, SubjectResultState $state): StudentSubjectResultData
    {
        return new StudentSubjectResultData($subjectName, $state, null, null, null, null, false);
    }

    private function published(string $subjectName, int $halfMarks, int $outOf, GradeScale $scale): StudentSubjectResultData
    {
        $band = $scale->bandFor($halfMarks, $outOf);

        return new StudentSubjectResultData(
            subject_name: $subjectName,
            state: SubjectResultState::Published,
            marks: MarkValue::format($halfMarks),
            percent: Percent::whole($halfMarks, $outOf),
            grade: $band->grade,
            grade_point: $band->gradePoint,
            is_lowest_grade: $scale->isLowest($band),
        );
    }
}
