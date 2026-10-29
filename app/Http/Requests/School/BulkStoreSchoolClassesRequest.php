<?php

namespace App\Http\Requests\School;

use App\Domain\School\Actions\CreateClassesInBulk;
use App\Domain\School\Models\AcademicYear;
use App\Domain\School\Models\Campus;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class BulkStoreSchoolClassesRequest extends FormRequest
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
        $levels = collect(config()->array('shiksha.class_ladder'))->pluck('level')->all();

        return [
            'academic_year' => ['required', 'string', Rule::exists(AcademicYear::class, 'ulid')],
            'campus' => ['required', 'string', Rule::exists(Campus::class, 'ulid')],
            'from_level' => ['required', 'integer', Rule::in($levels)],
            'to_level' => ['required', 'integer', Rule::in($levels), 'gte:from_level'],
            'sections' => ['required', 'integer', 'between:1,'.CreateClassesInBulk::MAX_SECTIONS],
            'naming' => ['required', Rule::in(['letters', 'numbers'])],
            'capacity' => ['nullable', 'integer', 'min:1', 'max:500'],
            'overrides' => ['nullable', 'array'],
            'overrides.*' => ['integer', 'between:1,'.CreateClassesInBulk::MAX_SECTIONS],
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

    /**
     * @return array<int, int> Section count by class level
     */
    public function overrides(): array
    {
        $overrides = [];

        foreach ((array) $this->input('overrides', []) as $level => $count) {
            $overrides[(int) $level] = (int) $count;
        }

        return $overrides;
    }
}
