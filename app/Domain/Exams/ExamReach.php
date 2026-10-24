<?php

namespace App\Domain\Exams;

use App\Domain\Access\Data\ScopeSet;
use App\Domain\Access\Data\ScopeTarget;
use App\Domain\Access\Queries\PermissionScopesQuery;
use App\Domain\Exams\Exceptions\ExamException;
use App\Domain\Exams\Models\Exam;
use App\Domain\Identity\Models\Identity;
use Illuminate\Support\Facades\DB;

/**
 * Who may reach which exams. An exam covers every pair of (class, campus) it
 * applies to; a person may change it only when their `exams.schedule` scope
 * covers every one of those pairs (a wing coordinator changes exams that sit
 * entirely inside the wing), and may see it when it touches a section they
 * reach. Out-of-reach exams are shown as not found.
 */
class ExamReach
{
    /** @var list<array{id: int, ulid: string, name: string, level: int}>|null */
    private ?array $classes = null;

    /** @var list<array{id: int, ulid: string, name: string}>|null */
    private ?array $campuses = null;

    /** @var list<array{class_id: int, campus_id: int, level: int, campus_ulid: string, section_ulid: string}>|null */
    private ?array $sections = null;

    public function __construct(private readonly PermissionScopesQuery $scopes) {}

    public function scopeFor(Identity $actor, string $permission): ScopeSet
    {
        return $this->scopes->get($actor, $permission);
    }

    /**
     * @return list<array{id: int, ulid: string, name: string, level: int}>
     */
    public function classes(): array
    {
        return $this->classes ??= array_values(DB::connection('tenant')->table('school_classes')->orderBy('level')
            ->get(['id', 'ulid', 'name', 'level'])
            ->map(fn ($row) => ['id' => (int) $row->id, 'ulid' => (string) $row->ulid, 'name' => (string) $row->name, 'level' => (int) $row->level])
            ->all());
    }

    /**
     * Active campuses.
     *
     * @return list<array{id: int, ulid: string, name: string}>
     */
    public function campuses(): array
    {
        return $this->campuses ??= array_values(DB::connection('tenant')->table('campuses')->where('status', 'active')->orderBy('name')
            ->get(['id', 'ulid', 'name'])
            ->map(fn ($row) => ['id' => (int) $row->id, 'ulid' => (string) $row->ulid, 'name' => (string) $row->name])
            ->all());
    }

    /**
     * Whether the scope covers every (class, campus) pair of the selection.
     *
     * @param  list<int>  $classIds
     * @param  list<int>  $campusIds
     */
    public function covers(ScopeSet $set, array $classIds, array $campusIds): bool
    {
        if ($set->isSchoolWide()) {
            return true;
        }

        $levels = $this->levelsById();
        $campusUlids = $this->campusUlidsById();

        foreach ($classIds as $classId) {
            foreach ($campusIds as $campusId) {
                if (! $set->allows(new ScopeTarget($campusUlids[$campusId] ?? null, $levels[$classId] ?? null))) {
                    return false;
                }
            }
        }

        return true;
    }

    /**
     * Whether the selection includes a section of this year the scope reaches.
     *
     * @param  list<int>  $classIds
     * @param  list<int>  $campusIds
     */
    public function touches(ScopeSet $set, array $classIds, array $campusIds): bool
    {
        foreach ($this->currentSections() as $section) {
            if (in_array($section['class_id'], $classIds, true)
                && in_array($section['campus_id'], $campusIds, true)
                && $set->allows(new ScopeTarget($section['campus_ulid'], $section['level'], $section['section_ulid']))) {
                return true;
            }
        }

        return false;
    }

    /**
     * Whether some active section of the current year matches the selection (no scope involved).
     *
     * @param  list<int>  $classIds
     * @param  list<int>  $campusIds
     */
    public function hasSection(array $classIds, array $campusIds): bool
    {
        foreach ($this->currentSections() as $section) {
            if (in_array($section['class_id'], $classIds, true) && in_array($section['campus_id'], $campusIds, true)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return list<int>
     */
    public function examClassIds(Exam $exam): array
    {
        if ($exam->applies_to_all_classes) {
            return array_column($this->classes(), 'id');
        }

        return $this->pivotIds('exam_school_class', 'school_class_id', $exam->id);
    }

    /**
     * @return list<int>
     */
    public function examCampusIds(Exam $exam): array
    {
        if ($exam->applies_to_all_campuses) {
            return array_column($this->campuses(), 'id');
        }

        return $this->pivotIds('campus_exam', 'campus_id', $exam->id);
    }

    public function maySee(Identity $actor, Exam $exam): bool
    {
        return $this->touches($this->scopeFor($actor, 'exams.schedule'), $this->examClassIds($exam), $this->examCampusIds($exam));
    }

    /**
     * Whether the person holds the permission over everything the exam applies to.
     */
    public function mayChange(Identity $actor, Exam $exam, string $permission = 'exams.schedule'): bool
    {
        return $this->covers($this->scopeFor($actor, $permission), $this->examClassIds($exam), $this->examCampusIds($exam));
    }

    /**
     * 404 for an exam outside the person's reach, 403 when they can see it but may not do this.
     */
    public function assertMayChange(Identity $actor, Exam $exam, string $permission = 'exams.schedule'): void
    {
        if (! $this->maySee($actor, $exam)) {
            throw ExamException::notFound();
        }

        if (! $this->mayChange($actor, $exam, $permission)) {
            throw ExamException::notAllowed();
        }
    }

    /**
     * @return array<int, int>
     */
    private function levelsById(): array
    {
        return array_column($this->classes(), 'level', 'id');
    }

    /**
     * @return array<int, string>
     */
    private function campusUlidsById(): array
    {
        return array_column($this->campuses(), 'ulid', 'id');
    }

    /**
     * @return list<array{class_id: int, campus_id: int, level: int, campus_ulid: string, section_ulid: string}>
     */
    private function currentSections(): array
    {
        return $this->sections ??= array_values(DB::connection('tenant')->table('sections')
            ->join('academic_years', 'academic_years.id', '=', 'sections.academic_year_id')
            ->join('campuses', 'campuses.id', '=', 'sections.campus_id')
            ->join('school_classes', 'school_classes.id', '=', 'sections.school_class_id')
            ->where('academic_years.is_current', true)
            ->where('sections.status', 'active')
            ->get(['sections.school_class_id', 'sections.campus_id', 'school_classes.level', 'campuses.ulid as campus_ulid', 'sections.ulid as section_ulid'])
            ->map(fn ($row) => [
                'class_id' => (int) $row->school_class_id,
                'campus_id' => (int) $row->campus_id,
                'level' => (int) $row->level,
                'campus_ulid' => (string) $row->campus_ulid,
                'section_ulid' => (string) $row->section_ulid,
            ])
            ->all());
    }

    /**
     * @return list<int>
     */
    private function pivotIds(string $table, string $column, int $examId): array
    {
        return array_values(DB::connection('tenant')->table($table)->where('exam_id', $examId)->pluck($column)
            ->map(fn ($id) => (int) $id)->all());
    }
}
