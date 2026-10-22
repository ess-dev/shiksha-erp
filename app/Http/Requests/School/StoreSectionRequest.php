<?php

namespace App\Http\Requests\School;

use App\Domain\Access\Data\ScopeTarget;
use App\Domain\School\Models\AcademicYear;
use App\Domain\School\Models\Campus;
use App\Domain\School\Models\SchoolClass;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class StoreSectionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Gate::allows('structure.manage');
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'academic_year' => ['required', 'string', Rule::exists(AcademicYear::class, 'ulid')],
            'campus' => ['required', 'string', Rule::exists(Campus::class, 'ulid')],
            'class' => ['required', 'string', Rule::exists(SchoolClass::class, 'ulid')],
            'name' => ['required', 'string', 'max:20'],
            'capacity' => ['nullable', 'integer', 'min:1', 'max:500'],
        ];
    }

    public function academicYear(): AcademicYear
    {
        return AcademicYear::query()->where('ulid', $this->string('academic_year')->toString())->firstOrFail();
    }

    public function campus(): Campus
    {
        return Campus::query()->where('ulid', $this->string('campus')->toString())->firstOrFail();
    }

    public function schoolClass(): SchoolClass
    {
        return SchoolClass::query()->where('ulid', $this->string('class')->toString())->firstOrFail();
    }

    /**
     * The section is checked against the campus and class level it sits at, so
     * a campus-scoped manager cannot add sections elsewhere.
     */
    protected function passedValidation(): void
    {
        Gate::authorize('structure.manage', [new ScopeTarget($this->campus()->ulid, $this->schoolClass()->level)]);
    }
}
