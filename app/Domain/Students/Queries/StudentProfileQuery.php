<?php

namespace App\Domain\Students\Queries;

use App\Domain\Access\Data\ScopeSet;
use App\Domain\Access\Data\ScopeTarget;
use App\Domain\Access\Queries\StaffNamesQuery;
use App\Domain\Identity\Models\Identity;
use App\Domain\School\Queries\SectionDirectoryQuery;
use App\Domain\Shared\PhoneNumber;
use App\Domain\Students\Data\EnrollmentHistoryData;
use App\Domain\Students\Data\GuardianData;
use App\Domain\Students\Data\GuardianEditData;
use App\Domain\Students\Data\SectionOptionData;
use App\Domain\Students\Data\SiblingData;
use App\Domain\Students\Data\StudentAbilitiesData;
use App\Domain\Students\Data\StudentDocumentData;
use App\Domain\Students\Data\StudentEditData;
use App\Domain\Students\Data\StudentPlace;
use App\Domain\Students\Data\StudentProfileData;
use App\Domain\Students\Enums\ParentAppStatus;
use App\Domain\Students\Enums\SiblingVia;
use App\Domain\Students\Exceptions\StudentException;
use App\Domain\Students\Models\Student;
use App\Domain\Students\Models\StudentDocument;
use App\Domain\Students\SiblingFinder;
use App\Domain\Students\StudentVisibility;
use Illuminate\Database\Connection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\URL;

class StudentProfileQuery
{
    public function __construct(
        private readonly StudentVisibility $visibility,
        private readonly SiblingFinder $siblings,
        private readonly SectionDirectoryQuery $sections,
        private readonly StaffNamesQuery $staffNames,
    ) {}

    /**
     * Everything the student profile shows, limited to what the identity may
     * see: phones masked without `students.view_sensitive`, documents only
     * with it (or `students.documents`), edit data and section choices only
     * with `students.manage`, and siblings or other children outside their
     * reach counted but not named.
     *
     * With `$yearId` (a past session) the profile shows the class and roll the
     * student had in that session, read only; a student not enrolled then is a 404.
     *
     * @throws StudentException 404 when the student is outside the identity's reach
     */
    public function get(Identity $identity, Student $student, ?int $yearId = null, ?string $yearName = null): StudentProfileData
    {
        $isPast = $yearId !== null;
        $viewSet = $this->visibility->scopes($identity, 'students.view');
        $target = $this->visibility->targets([$student->id], $yearId)[$student->id] ?? null;

        if ($target === null || ! $viewSet->allows($target)) {
            throw StudentException::notFound();
        }

        $sets = [];

        foreach (['manage' => 'students.manage', 'issue_tc' => 'students.issue_tc', 'documents' => 'students.documents', 'link_siblings' => 'students.link_siblings', 'view_sensitive' => 'students.view_sensitive'] as $key => $permission) {
            $sets[$key] = $this->visibility->scopes($identity, $permission);
        }

        $can = fn (string $key): bool => $sets[$key]->allows($target);
        $history = $this->history($student, $yearId);
        $place = $history['place'];
        $abilities = new StudentAbilitiesData(
            manage: ! $isPast && $can('manage'),
            issue_tc: ! $isPast && $can('issue_tc'),
            documents: ! $isPast && $can('documents'),
            link_siblings: ! $isPast && $can('link_siblings'),
            view_sensitive: $can('view_sensitive'),
        );

        [$siblings, $hiddenSiblings] = $this->siblingsOf($identity, $student);
        $guardians = $this->guardians($identity, $student, $abilities->view_sensitive);
        $houseName = $student->house_id === null ? null : (string) $this->db()->table('houses')->where('id', $student->house_id)->value('name');

        return new StudentProfileData(
            ulid: $student->ulid,
            name: $student->fullName(),
            admission_number: $student->admission_number,
            status: $student->status->value,
            is_current: $student->status->isCurrent(),
            class_label: $place?->classLabel,
            roll_number: $place?->rollNumber,
            campus_name: $place?->campusName,
            gender: $student->gender->label(),
            born_on: $student->born_on,
            blood_group: $student->blood_group->label(),
            house_name: $houseName,
            address: $student->address,
            admitted_on: $student->admitted_on,
            left_on: $student->left_on,
            leave_reason: $student->leave_reason,
            tc_number: $student->tc_number,
            siblings: $siblings,
            hidden_siblings: $hiddenSiblings,
            guardians: $guardians['display'],
            enrollments: $history['rows'],
            documents: ($abilities->view_sensitive || $abilities->documents) ? $this->documents($student, $abilities->view_sensitive) : null,
            abilities: $abilities,
            edit: $abilities->manage ? $this->edit($student, $place, $guardians['edit']) : null,
            session_name: $isPast ? $yearName : null,
            section_options: $abilities->manage && $student->status->isCurrent() ? $this->sectionOptions($sets['manage'], $target) : [],
        );
    }

    /**
     * @return array{rows: list<EnrollmentHistoryData>, place: StudentPlace|null}
     */
    private function history(Student $student, ?int $yearId = null): array
    {
        $records = $this->db()->table('enrollments as e')
            ->join('academic_years as ay', 'ay.id', '=', 'e.academic_year_id')
            ->join('sections as s', 's.id', '=', 'e.section_id')
            ->join('school_classes as sc', 'sc.id', '=', 's.school_class_id')
            ->join('campuses as c', 'c.id', '=', 's.campus_id')
            ->where('e.student_id', $student->id)
            ->orderByDesc('e.id')
            ->get(['e.id', 'e.academic_year_id', 'e.roll_number', 'e.started_on', 'e.ended_on', 'e.result', 'e.note', 'e.is_current', 'ay.name as year_name', 'sc.name as class_name', 's.name as section_name', 'c.name as campus_name']);

        $rows = array_values($records->map(fn (object $row) => new EnrollmentHistoryData(
            academic_year: (string) $row->year_name,
            class_label: $row->class_name.'-'.$row->section_name,
            campus_name: (string) $row->campus_name,
            result: (string) $row->result,
            is_current: (bool) $row->is_current,
            note: $row->note,
            started_on: (string) $row->started_on,
            ended_on: $row->ended_on,
        ))->all());

        $latest = $yearId !== null
            ? $records->firstWhere('academic_year_id', $yearId)
            : ($records->firstWhere('is_current', true) ?? $records->first());

        return [
            'rows' => $rows,
            'place' => $latest === null ? null : new StudentPlace(
                $latest->class_name.'-'.$latest->section_name,
                $latest->roll_number === null ? null : (int) $latest->roll_number,
                (string) $latest->campus_name,
            ),
        ];
    }

    /**
     * Siblings the viewer may see by name, and how many more exist outside their reach.
     *
     * @return array{0: list<SiblingData>, 1: int}
     */
    private function siblingsOf(Identity $identity, Student $student): array
    {
        $ids = $this->siblings->idsFor($student->id);

        if ($ids === []) {
            return [[], 0];
        }

        $visible = $this->visibility->query($identity)
            ?->whereIn('students.id', $ids)
            ->addSelect(['sc.name as class_name', 's.name as section_name', 'c.name as campus_name'])
            ->orderBy('students.first_name')
            ->get() ?? collect();

        $result = [];

        foreach ($visible as $sibling) {
            $result[] = new SiblingData(
                ulid: $sibling->ulid,
                name: $sibling->fullName(),
                class_label: $sibling->getAttribute('class_name').'-'.$sibling->getAttribute('section_name'),
                campus_name: (string) $sibling->getAttribute('campus_name'),
                via: $this->siblings->sharesGuardian($student->id, $sibling->id) ? SiblingVia::Guardian : SiblingVia::Staff,
            );
        }

        return [$result, count($ids) - count($result)];
    }

    /**
     * @return array{display: list<GuardianData>, edit: list<GuardianEditData>}
     */
    private function guardians(Identity $identity, Student $student, bool $canSeePhones): array
    {
        $rows = $this->db()->table('guardian_student as gs')
            ->join('guardians as g', 'g.id', '=', 'gs.guardian_id')
            ->where('gs.student_id', $student->id)
            ->orderByDesc('gs.is_primary')
            ->orderBy('gs.id')
            ->get(['g.id', 'g.ulid', 'g.name', 'g.phone', 'g.email', 'g.occupation', 'g.identity_ulid', 'gs.relation', 'gs.is_primary', 'gs.has_portal_access']);

        $otherChildren = $this->db()->table('guardian_student')
            ->whereIn('guardian_id', $rows->pluck('id')->all())
            ->where('student_id', '!=', $student->id)
            ->get(['guardian_id', 'student_id'])
            ->groupBy('guardian_id');

        $visibleNames = $this->visibility->query($identity)
            ?->whereIn('students.id', $otherChildren->flatten(1)->pluck('student_id')->all())
            ->get()
            ->mapWithKeys(fn (Student $child) => [$child->id => $child->first_name]) ?? collect();

        $display = [];
        $edit = [];

        foreach ($rows as $row) {
            $children = $otherChildren->get($row->id, collect())->pluck('student_id');
            $names = $children->filter(fn (mixed $id) => $visibleNames->has($id))->map(fn (mixed $id) => (string) $visibleNames->get($id))->values()->all();

            $status = ! $row->has_portal_access
                ? ParentAppStatus::NotInvited
                : ($row->identity_ulid !== null ? ParentAppStatus::Active : ParentAppStatus::Invited);

            $display[] = new GuardianData(
                ulid: $row->ulid,
                name: $row->name,
                relation: $row->relation,
                is_primary: (bool) $row->is_primary,
                phone: $canSeePhones ? $row->phone : PhoneNumber::mask($row->phone),
                email: $row->email,
                occupation: $row->occupation,
                has_portal_access: (bool) $row->has_portal_access,
                parent_app: $status,
                other_children: array_values($names),
                hidden_other_children: $children->count() - count($names),
            );

            $edit[] = new GuardianEditData(
                ulid: $row->ulid,
                name: $row->name,
                relation: $row->relation,
                phone: $row->phone,
                email: (string) $row->email,
                occupation: (string) $row->occupation,
                is_primary: (bool) $row->is_primary,
                has_portal_access: (bool) $row->has_portal_access,
            );
        }

        return ['display' => $display, 'edit' => $edit];
    }

    /**
     * @return list<StudentDocumentData>
     */
    private function documents(Student $student, bool $canOpen): array
    {
        $documents = StudentDocument::query()
            ->where('student_id', $student->id)
            ->whereNull('superseded_at')
            ->orderBy('kind')
            ->orderByDesc('id')
            ->get();

        $verifiers = [];

        foreach ($documents as $document) {
            if ($document->verified_by_identity_ulid !== null) {
                $verifiers[] = $document->verified_by_identity_ulid;
            }
        }

        $names = $this->staffNames->forIdentities($verifiers);

        return array_values($documents->map(fn (StudentDocument $document) => new StudentDocumentData(
            ulid: $document->ulid,
            kind: $document->kind->value,
            label: $document->kind->label(),
            original_name: $document->original_name,
            is_verified: $document->isVerified(),
            verified_by: $document->verified_by_identity_ulid === null ? null : ($names[$document->verified_by_identity_ulid] ?? null),
            uploaded_on: $document->created_at->format('Y-m-d'),
            source: $document->source,
            view_url: $canOpen ? URL::temporarySignedRoute('students.documents.show', now()->addMinutes(5), ['document' => $document->ulid]) : null,
        ))->all());
    }

    /**
     * @param  list<GuardianEditData>  $guardians
     */
    private function edit(Student $student, ?StudentPlace $place, array $guardians): StudentEditData
    {
        $houseUlid = $student->house_id === null ? '' : (string) $this->db()->table('houses')->where('id', $student->house_id)->value('ulid');

        return new StudentEditData(
            first_name: $student->first_name,
            last_name: $student->last_name,
            gender: $student->gender->value,
            born_on: $student->born_on,
            blood_group: $student->blood_group->value,
            house_ulid: $houseUlid,
            address: $student->address,
            roll_number: $place?->rollNumber,
            guardians: $guardians,
        );
    }

    /**
     * Other sections of the student's class the viewer may move them to.
     *
     * @return list<SectionOptionData>
     */
    private function sectionOptions(ScopeSet $manage, ScopeTarget $from): array
    {
        $options = [];

        foreach ($this->sections->currentYear($from->classLevel) as $section) {
            if ($section->ulid === $from->sectionUlid || ! $manage->allows($section->scopeTarget())) {
                continue;
            }

            $options[] = new SectionOptionData(
                ulid: $section->ulid,
                label: $section->label(),
                campus_name: $section->campusName,
                enrolled: $section->enrolledCount,
                capacity: $section->capacity,
                is_full: $section->isFull(),
            );
        }

        return $options;
    }

    private function db(): Connection
    {
        return DB::connection(config('multitenancy.tenant_database_connection_name'));
    }
}
