<?php

namespace App\Http\Requests\Students;

use App\Domain\School\Queries\HousesQuery;
use App\Domain\Students\Data\GuardianUpdate;
use App\Domain\Students\Data\StudentDetailsUpdate;
use App\Domain\Students\Enums\BloodGroup;
use App\Domain\Students\Enums\Gender;
use App\Domain\Students\Models\Student;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class UpdateStudentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Gate::allows('students.manage');
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'first_name' => ['required', 'string', 'max:100'],
            'last_name' => ['required', 'string', 'max:100'],
            'gender' => ['required', Rule::enum(Gender::class)],
            'born_on' => ['required', 'date_format:Y-m-d', 'before_or_equal:today'],
            'blood_group' => ['required', Rule::enum(BloodGroup::class)],
            'house' => ['nullable', 'string', 'max:26'],
            'address' => ['required', 'string', 'max:500'],
            'guardians' => ['required', 'array', 'min:1', 'max:6'],
            'guardians.*.ulid' => ['required', 'string', 'max:26'],
            'guardians.*.name' => ['required', 'string', 'max:150'],
            'guardians.*.relation' => ['required', 'string', 'max:50'],
            'guardians.*.phone' => ['required', 'string', 'max:20'],
            'guardians.*.email' => ['nullable', 'email', 'max:190'],
            'guardians.*.occupation' => ['nullable', 'string', 'max:100'],
            'guardians.*.is_primary' => ['required', 'boolean'],
            'guardians.*.has_portal_access' => ['required', 'boolean'],
        ];
    }

    /**
     * A house must be active, except the one the student already has.
     *
     * @return list<callable(Validator): void>
     */
    public function after(): array
    {
        return [function (Validator $validator) {
            $ulid = $this->input('house');

            if (! is_string($ulid) || $ulid === '') {
                return;
            }

            $houses = app(HousesQuery::class);
            $student = $this->route('student');
            $current = $houses->anyKeyByUlid($ulid);
            $allowed = $houses->activeKeyByUlid($ulid) !== null
                || ($student instanceof Student && $current !== null && $current === $student->house_id);

            if (! $allowed) {
                $validator->errors()->add('house', __('Choose one of the active houses.'));
            }
        }];
    }

    public function toUpdate(): StudentDetailsUpdate
    {
        $ulid = $this->input('house');
        $houseKey = is_string($ulid) && $ulid !== '' ? app(HousesQuery::class)->anyKeyByUlid($ulid) : null;

        return new StudentDetailsUpdate(
            firstName: $this->string('first_name')->toString(),
            lastName: $this->string('last_name')->toString(),
            gender: Gender::from($this->string('gender')->toString()),
            bornOn: $this->string('born_on')->toString(),
            bloodGroup: BloodGroup::from($this->string('blood_group')->toString()),
            houseKey: $houseKey,
            address: $this->string('address')->toString(),
            guardians: array_values(array_map(fn (array $guardian) => new GuardianUpdate(
                ulid: (string) $guardian['ulid'],
                name: (string) $guardian['name'],
                relation: (string) $guardian['relation'],
                phone: (string) $guardian['phone'],
                email: isset($guardian['email']) ? (string) $guardian['email'] : null,
                occupation: isset($guardian['occupation']) ? (string) $guardian['occupation'] : null,
                isPrimary: (bool) $guardian['is_primary'],
                hasPortalAccess: (bool) $guardian['has_portal_access'],
            ), (array) $this->input('guardians'))),
        );
    }
}
