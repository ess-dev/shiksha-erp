<?php

namespace App\Domain\Students\Actions;

use App\Domain\Audit\Data\AuditEntry;
use App\Domain\Identity\Models\Identity;
use App\Domain\Shared\Action;
use App\Domain\Shared\CalendarDate;
use App\Domain\Shared\PhoneNumber;
use App\Domain\Students\Data\StudentDetailsUpdate;
use App\Domain\Students\Exceptions\StudentException;
use App\Domain\Students\Models\Guardian;
use App\Domain\Students\Models\Student;
use App\Domain\Students\StudentGuard;
use App\Domain\Students\StudentVisibility;
use Illuminate\Support\Facades\DB;

class UpdateStudentDetails extends Action
{
    public function __construct(
        private readonly StudentGuard $guard,
        private readonly StudentVisibility $visibility,
    ) {}

    /**
     * Edits a student's details and their guardians' details. Needs
     * `students.manage` over the student. A guardian is shared by all their
     * children, so changing a guardian's own details (name, phone, email,
     * occupation) also needs reach over every child they are linked to. A
     * phone number already held by another guardian cannot be taken.
     */
    public function handle(Identity $actor, Student $student, StudentDetailsUpdate $update): Student
    {
        $this->guard->assertMayOn($actor, 'students.manage', $student);

        return $this->transaction(function () use ($actor, $student, $update) {
            $student->refresh();

            $before = $student->only(['first_name', 'last_name', 'gender', 'born_on', 'blood_group', 'house_id', 'address']);

            $student->fill([
                'first_name' => trim($update->firstName),
                'last_name' => trim($update->lastName),
                'gender' => $update->gender,
                'born_on' => CalendarDate::parse($update->bornOn),
                'blood_group' => $update->bloodGroup,
                'house_id' => $update->houseKey,
                'address' => trim($update->address),
            ]);

            $changed = array_keys($student->getDirty());
            $student->save();

            $guardianChanges = $this->updateGuardians($actor, $student, $update);

            $this->audit(new AuditEntry(
                'students.student_updated',
                'student',
                $student->ulid,
                array_intersect_key($before, array_flip($changed)),
                [
                    ...array_intersect_key($student->only(['first_name', 'last_name', 'gender', 'born_on', 'blood_group', 'house_id', 'address']), array_flip($changed)),
                    'guardians' => $guardianChanges,
                ],
            ));

            return $student;
        });
    }

    /**
     * @return list<array{guardian: string, changed: list<string>}>
     */
    private function updateGuardians(Identity $actor, Student $student, StudentDetailsUpdate $update): array
    {
        $links = $student->guardians()->get()->keyBy('ulid');
        $given = collect($update->guardians)->keyBy('ulid');

        if ($given->count() !== $links->count() || $given->keys()->diff($links->keys())->isNotEmpty()) {
            throw new StudentException('List every guardian of this student exactly once.');
        }

        $phones = [];
        $primaries = $given->filter(fn ($entry) => $entry->isPrimary)->count();

        if ($primaries !== 1) {
            throw new StudentException('Choose exactly one primary contact.');
        }

        $changes = [];

        foreach ($given as $ulid => $entry) {
            /** @var Guardian $guardian */
            $guardian = $links[$ulid];

            $phone = PhoneNumber::tryParse($entry->phone)->e164 ?? throw StudentException::invalidPhone($entry->name);

            if (isset($phones[$phone])) {
                throw StudentException::guardiansShareAPhone();
            }

            $phones[$phone] = true;

            $other = Guardian::query()->where('phone', $phone)->where('id', '!=', $guardian->id)->first();

            if ($other !== null) {
                throw StudentException::guardianPhoneConflict($other->name);
            }

            $guardian->fill([
                'name' => trim($entry->name),
                'phone' => $phone,
                'email' => $this->blankToNull($entry->email),
                'occupation' => $this->blankToNull($entry->occupation),
            ]);

            $fields = array_keys($guardian->getDirty());

            if ($fields !== []) {
                $this->assertReachesEveryChild($actor, $guardian);
                $guardian->save();
            }

            $pivot = $student->guardians()->where('guardians.id', $guardian->id)->first()?->pivot;

            $linkChanged = $pivot !== null && (
                $pivot->relation !== trim($entry->relation)
                || (bool) $pivot->is_primary !== $entry->isPrimary
                || (bool) $pivot->has_portal_access !== $entry->hasPortalAccess
            );

            if ($linkChanged) {
                $student->guardians()->updateExistingPivot($guardian->id, [
                    'relation' => trim($entry->relation) !== '' ? trim($entry->relation) : 'Guardian',
                    'is_primary' => $entry->isPrimary,
                    'has_portal_access' => $entry->hasPortalAccess,
                ]);

                $fields[] = 'link';
            }

            if ($fields !== []) {
                $changes[] = ['guardian' => $guardian->ulid, 'changed' => $fields];
            }
        }

        return $changes;
    }

    private function assertReachesEveryChild(Identity $actor, Guardian $guardian): void
    {
        $childIds = DB::connection(config('multitenancy.tenant_database_connection_name'))
            ->table('guardian_student')->where('guardian_id', $guardian->id)->pluck('student_id')
            ->map(fn (mixed $id): int => (int) $id)->all();

        $set = $this->visibility->scopes($actor, 'students.manage');

        foreach ($this->visibility->targets(array_values($childIds)) as $target) {
            if (! $set->allows($target)) {
                throw StudentException::guardianOutOfReach($guardian->getOriginal('name'));
            }
        }
    }

    private function blankToNull(?string $value): ?string
    {
        $value = $value === null ? null : trim($value);

        return $value === '' ? null : $value;
    }
}
