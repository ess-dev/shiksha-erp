<?php

namespace App\Domain\Students\Queries;

use App\Domain\Access\Data\ScopeTarget;
use App\Domain\Identity\Models\Identity;
use App\Domain\Shared\CalendarDate;
use App\Domain\Shared\PhoneNumber;
use App\Domain\Students\Data\StudentFilters;
use App\Domain\Students\Data\StudentListData;
use App\Domain\Students\Data\StudentRowData;
use App\Domain\Students\Enums\StudentStatus;
use App\Domain\Students\Models\Student;
use App\Domain\Students\SiblingFinder;
use App\Domain\Students\StudentVisibility;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use stdClass;

class StudentsQuery
{
    public const PER_PAGE = 25;

    public function __construct(
        private readonly StudentVisibility $visibility,
        private readonly SiblingFinder $siblings,
    ) {}

    /**
     * One page of the students the identity may see (their `students.view`
     * scope), filtered and sorted like the prototype: campus, class, section,
     * roll. Phones are masked unless the person holds `students.view_sensitive`
     * over that student. Null when the permission is not held.
     */
    public function paginate(Identity $identity, StudentFilters $filters, int $page = 1): ?StudentListData
    {
        $query = $this->filtered($identity, $filters, 'students.view');

        if ($query === null) {
            return null;
        }

        $paginator = $query
            ->addSelect(['e.roll_number', 's.name as section_name', 'sc.name as class_name', 'sc.level as class_level', 'c.name as campus_name', 'c.ulid as campus_ulid', 's.ulid as section_ulid'])
            ->paginate(self::PER_PAGE, page: max($page, 1));

        /** @var list<Student> $students */
        $students = $paginator->items();
        $ids = array_map(fn (Student $student): int => $student->id, $students);

        $guardians = $this->primaryGuardians($ids);
        $withSiblings = $this->siblings->withSiblings($ids);
        $sensitive = $this->visibility->scopes($identity, 'students.view_sensitive');
        $today = CalendarDate::today();

        $rows = array_map(function (Student $student) use ($guardians, $withSiblings, $sensitive, $today) {
            $guardian = $guardians[$student->id] ?? null;
            $target = new ScopeTarget((string) $student->getAttribute('campus_ulid'), (int) $student->getAttribute('class_level'), (string) $student->getAttribute('section_ulid'));

            return new StudentRowData(
                ulid: $student->ulid,
                name: $student->fullName(),
                admission_number: $student->admission_number,
                status: $student->status->value,
                class_label: $this->classLabel($student),
                roll_number: $student->getAttribute('roll_number') === null ? null : (int) $student->getAttribute('roll_number'),
                campus_name: (string) $student->getAttribute('campus_name'),
                guardian_name: $guardian?->name,
                guardian_relation: $guardian?->relation,
                guardian_phone: $guardian === null ? null : ($sensitive->allows($target) ? $guardian->phone : PhoneNumber::mask($guardian->phone)),
                has_sibling: isset($withSiblings[$student->id]),
                is_new: $student->admitted_on === $today,
            );
        }, $students);

        $totalCurrent = $this->filtered($identity, new StudentFilters(status: 'current', campusUlid: $filters->campusUlid, yearId: $filters->yearId), 'students.view')?->reorder()->count() ?? 0;

        return new StudentListData(
            rows: $rows,
            total: $paginator->total(),
            from: $paginator->firstItem() ?? 0,
            to: $paginator->lastItem() ?? 0,
            current_page: $paginator->currentPage(),
            last_page: $paginator->lastPage(),
            current_students_total: $totalCurrent,
        );
    }

    /**
     * Every student matching the filters under the identity's `students.export`
     * scope, as CSV rows (header first) with full phone numbers. Spreadsheet
     * safe: phones are written with the country code but without "+".
     *
     * @return list<list<string>>|null Null when the permission is not held
     */
    public function exportRows(Identity $identity, StudentFilters $filters): ?array
    {
        $query = $this->filtered($identity, $filters, 'students.export');

        if ($query === null) {
            return null;
        }

        $students = $query
            ->addSelect(['e.roll_number', 's.name as section_name', 'sc.name as class_name', 'c.name as campus_name', 'h.name as house_name'])
            ->get();

        $guardians = $this->primaryGuardians(array_values($students->pluck('id')->map(fn (mixed $id): int => (int) $id)->all()));

        $rows = [['Admission no', 'Name', 'Class', 'Roll', 'Campus', 'Status', 'Gender', 'Date of birth', 'Blood group', 'House', 'Primary guardian', 'Relation', 'Phone (with country code)', 'Email', 'Address']];

        foreach ($students as $student) {
            $guardian = $guardians[$student->id] ?? null;

            $rows[] = [
                $student->admission_number,
                $student->fullName(),
                (string) $this->classLabel($student),
                (string) ($student->getAttribute('roll_number') ?? ''),
                (string) $student->getAttribute('campus_name'),
                $student->status->value,
                $student->gender->label(),
                $student->born_on,
                $student->blood_group->label(),
                (string) ($student->getAttribute('house_name') ?? 'Not assigned'),
                $guardian->name ?? '',
                $guardian->relation ?? '',
                $guardian === null ? '' : ltrim($guardian->phone, '+'),
                $guardian->email ?? '',
                $student->address,
            ];
        }

        return $rows;
    }

    /**
     * @return Builder<Student>|null
     */
    private function filtered(Identity $identity, StudentFilters $filters, string $permission): ?Builder
    {
        $query = $this->visibility->query($identity, $permission, $filters->yearId);

        if ($query === null) {
            return null;
        }

        $query->leftJoin('houses as h', 'h.id', '=', 'students.house_id');

        if ($filters->yearId === null) {
            match ($filters->status) {
                'left' => $query->whereIn('students.status', array_map(fn (StudentStatus $status) => $status->value, StudentStatus::leftSchool())),
                'all' => null,
                default => $query->where('students.status', StudentStatus::Enrolled->value),
            };
        }

        if ($filters->sectionUlid !== null) {
            $query->where('s.ulid', $filters->sectionUlid);
        }

        if ($filters->campusUlid !== null) {
            $query->where('c.ulid', $filters->campusUlid);
        }

        $term = trim($filters->search);

        if ($term !== '') {
            $like = '%'.mb_strtolower(addcslashes($term, '%_\\')).'%';
            $digits = preg_replace('/\D/', '', $term) ?? '';
            // Searching by phone would let someone with masked numbers read them one digit at a time.
            $canSearchPhone = strlen($digits) >= 3 && $this->visibility->scopes($identity, 'students.view_sensitive')->allows();

            $query->where(function (Builder $where) use ($like, $digits, $canSearchPhone) {
                $where->whereRaw("lower(students.first_name || ' ' || students.last_name) like ?", [$like])
                    ->orWhereRaw('lower(students.admission_number) like ?', [$like]);

                if ($canSearchPhone) {
                    $where->orWhereExists(function ($sub) use ($digits) {
                        $sub->select(DB::raw(1))
                            ->from('guardian_student as gs')
                            ->join('guardians as g', 'g.id', '=', 'gs.guardian_id')
                            ->whereColumn('gs.student_id', 'students.id')
                            ->where('g.phone', 'like', '%'.$digits.'%');
                    });
                }
            });
        }

        return $query
            ->orderBy('c.name')
            ->orderBy('sc.level')
            ->orderBy('s.name')
            ->orderByRaw('e.roll_number asc nulls last')
            ->orderBy('students.last_name')
            ->orderBy('students.first_name');
    }

    private function classLabel(Student $student): ?string
    {
        $class = $student->getAttribute('class_name');

        return $class === null ? null : $class.'-'.$student->getAttribute('section_name');
    }

    /**
     * The primary guardian of each student (the first one flagged primary).
     *
     * @param  list<int>  $studentIds
     * @return array<int, stdClass>
     */
    private function primaryGuardians(array $studentIds): array
    {
        if ($studentIds === []) {
            return [];
        }

        $rows = DB::connection(config('multitenancy.tenant_database_connection_name'))->table('guardian_student as gs')
            ->join('guardians as g', 'g.id', '=', 'gs.guardian_id')
            ->whereIn('gs.student_id', $studentIds)
            ->orderByDesc('gs.is_primary')
            ->orderBy('gs.id')
            ->get(['gs.student_id', 'g.name', 'gs.relation', 'g.phone', 'g.email']);

        $result = [];

        foreach ($rows as $row) {
            $result[(int) $row->student_id] ??= $row;
        }

        return $result;
    }
}
