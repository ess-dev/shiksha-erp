<?php

namespace App\Domain\Exams;

use App\Domain\Access\Queries\PermissionScopesQuery;
use App\Domain\Access\Queries\StaffRefQuery;
use App\Domain\Exams\Enums\ExamStatus;
use App\Domain\Exams\Enums\MarksReadiness;
use App\Domain\Exams\Exceptions\ReportCardException;
use App\Domain\Exams\Models\Exam;
use App\Domain\Identity\Models\Identity;
use App\Domain\School\Data\SectionRefData;
use App\Domain\School\Queries\SectionDirectoryQuery;
use Illuminate\Support\Facades\DB;

/**
 * Who may prepare and approve which report cards, in one place so the screen
 * and the Actions cannot disagree. Current academic year only.
 *
 * A section's report cards are prepared by its class teacher, who must hold
 * `reportcards.prepare` over it. They are approved (or sent back) by anyone
 * holding `reportcards.approve` over the section. A report card is for an
 * exam with marking open or results published that applies to the section,
 * and only from marks that are entered and published for every examined
 * subject, so a report card never shows parents marks they cannot see yet.
 */
class ReportCardAccess
{
    public function __construct(
        private readonly PermissionScopesQuery $scopes,
        private readonly StaffRefQuery $staff,
        private readonly SectionDirectoryQuery $sections,
        private readonly ExamReach $reach,
    ) {}

    public function mayOpen(Identity $actor): bool
    {
        return $this->scopes->get($actor, 'reportcards.prepare')->allows();
    }

    /**
     * Sections of the current year whose class teacher is the person and
     * which their `reportcards.prepare` covers, lowest class first.
     *
     * @return list<SectionRefData>
     */
    public function preparerSections(Identity $actor): array
    {
        $prepare = $this->scopes->get($actor, 'reportcards.prepare');
        $me = $this->staff->forIdentity($actor);

        if ($prepare->isEmpty() || $me === null || ! $me->isActive) {
            return [];
        }

        $sections = array_values(array_filter(
            $this->sections->currentYear(),
            fn (SectionRefData $section) => $section->classTeacherStaffKey === $me->staffKey && $prepare->allows($section->scopeTarget()),
        ));

        usort($sections, fn (SectionRefData $a, SectionRefData $b) => [$a->classLevel, $a->campusName, $a->name] <=> [$b->classLevel, $b->campusName, $b->name]);

        return $sections;
    }

    /**
     * The person's section for a change, or a 404.
     */
    public function preparerSectionOrFail(Identity $actor, string $sectionUlid): SectionRefData
    {
        foreach ($this->preparerSections($actor) as $section) {
            if ($section->ulid === $sectionUlid) {
                return $section;
            }
        }

        throw ReportCardException::notFound();
    }

    public function mayApproveAny(Identity $actor): bool
    {
        return $this->scopes->get($actor, 'reportcards.approve')->allows();
    }

    /**
     * Current-year sections the person's `reportcards.approve` covers, lowest
     * class first.
     *
     * @return list<SectionRefData>
     */
    public function approverSections(Identity $actor): array
    {
        $approve = $this->scopes->get($actor, 'reportcards.approve');

        if ($approve->isEmpty()) {
            return [];
        }

        $sections = array_values(array_filter(
            $this->sections->currentYear(),
            fn (SectionRefData $section) => $approve->allows($section->scopeTarget()),
        ));

        usort($sections, fn (SectionRefData $a, SectionRefData $b) => [$a->classLevel, $a->campusName, $a->name] <=> [$b->classLevel, $b->campusName, $b->name]);

        return $sections;
    }

    /**
     * A current-year section the person may approve report cards for, or a 404.
     */
    public function approverSectionOrFail(Identity $actor, string $sectionUlid): SectionRefData
    {
        $approve = $this->scopes->get($actor, 'reportcards.approve');

        foreach ($this->sections->currentYear() as $section) {
            if ($section->ulid === $sectionUlid && $approve->allows($section->scopeTarget())) {
                return $section;
            }
        }

        throw ReportCardException::notFound();
    }

    public function mayApprove(Identity $actor, SectionRefData $section): bool
    {
        return $this->scopes->get($actor, 'reportcards.approve')->allows($section->scopeTarget());
    }

    /**
     * Exams of the section's year with marking open or results published
     * that apply to its class at its campus, earliest first.
     *
     * @return list<Exam>
     */
    public function examsFor(SectionRefData $section): array
    {
        $classKey = (int) (array_column($this->reach->classes(), 'id', 'ulid')[$section->classUlid] ?? 0);
        $campusKey = (int) DB::connection(config('multitenancy.tenant_database_connection_name'))->table('campuses')->where('ulid', $section->campusUlid)->value('id');

        return array_values(Exam::query()
            ->where('academic_year_id', $section->academicYearKey)
            ->whereIn('status', [ExamStatus::Marking->value, ExamStatus::Published->value])
            ->orderBy('starts_on')
            ->orderBy('id')
            ->get()
            ->filter(fn (Exam $exam) => in_array($classKey, $this->reach->examClassIds($exam), true)
                && in_array($campusKey, $this->reach->examCampusIds($exam), true))
            ->all());
    }

    public function examOrFail(SectionRefData $section, Exam $exam): Exam
    {
        foreach ($this->examsFor($section) as $candidate) {
            if ($candidate->id === $exam->id) {
                return $candidate;
            }
        }

        throw ReportCardException::notFound();
    }

    /**
     * The examined subjects each student has no marks for, by name, so the
     * class teacher knows what to chase. Same subjects as `readiness()`.
     *
     * @param  list<int>  $studentKeys
     * @return array<int, list<string>> by student key
     */
    public function missingSubjects(Exam $exam, SectionRefData $section, array $studentKeys): array
    {
        $connection = DB::connection(config('multitenancy.tenant_database_connection_name'));
        $classKey = (int) (array_column($this->reach->classes(), 'id', 'ulid')[$section->classUlid] ?? 0);

        $subjects = $connection->table('school_class_subject as link')
            ->join('subjects', 'subjects.id', '=', 'link.subject_id')
            ->where('link.school_class_id', $classKey)
            ->where('subjects.status', 'active')
            ->where('subjects.type', '!=', 'co_scholastic')
            ->orderBy('subjects.name')
            ->pluck('subjects.name', 'subjects.id')
            ->all();

        $marked = [];

        foreach ($connection->table('exam_marks')->where('exam_id', $exam->id)->whereIn('student_id', $studentKeys)
            ->get(['student_id', 'subject_id']) as $row) {
            $marked[(int) $row->student_id][(int) $row->subject_id] = true;
        }

        $missing = [];

        foreach ($studentKeys as $studentKey) {
            $missing[$studentKey] = [];

            foreach ($subjects as $subjectKey => $name) {
                if (! isset($marked[$studentKey][(int) $subjectKey])) {
                    $missing[$studentKey][] = (string) $name;
                }
            }
        }

        return $missing;
    }

    /**
     * How ready each student's marks are for a report card.
     *
     * @param  list<int>  $studentKeys
     * @return array<int, MarksReadiness> by student key
     */
    public function readiness(Exam $exam, SectionRefData $section, array $studentKeys): array
    {
        $connection = DB::connection(config('multitenancy.tenant_database_connection_name'));
        $classKey = (int) (array_column($this->reach->classes(), 'id', 'ulid')[$section->classUlid] ?? 0);

        $subjectKeys = array_map(intval(...), $connection->table('school_class_subject as link')
            ->join('subjects', 'subjects.id', '=', 'link.subject_id')
            ->where('link.school_class_id', $classKey)
            ->where('subjects.status', 'active')
            ->where('subjects.type', '!=', 'co_scholastic')
            ->pluck('subjects.id')
            ->all());

        $marked = [];

        foreach ($connection->table('exam_marks')->where('exam_id', $exam->id)->whereIn('student_id', $studentKeys)
            ->get(['student_id', 'subject_id', 'section_id']) as $row) {
            $marked[(int) $row->student_id][(int) $row->subject_id] = (int) $row->section_id;
        }

        $published = [];

        foreach ($connection->table('exam_mark_sheets')->where('exam_id', $exam->id)->whereNotNull('published_at')
            ->get(['section_id', 'subject_id']) as $row) {
            $published[$row->section_id.'|'.$row->subject_id] = true;
        }

        $states = [];

        foreach ($studentKeys as $studentKey) {
            $state = $subjectKeys === [] ? MarksReadiness::Missing : MarksReadiness::Ready;

            foreach ($subjectKeys as $subjectKey) {
                if (! isset($marked[$studentKey][$subjectKey])) {
                    $state = MarksReadiness::Missing;

                    break;
                }

                if ($exam->status !== ExamStatus::Published && ! isset($published[$marked[$studentKey][$subjectKey].'|'.$subjectKey])) {
                    $state = MarksReadiness::Unpublished;
                }
            }

            $states[$studentKey] = $state;
        }

        return $states;
    }
}
