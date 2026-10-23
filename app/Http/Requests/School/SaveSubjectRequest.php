<?php

namespace App\Http\Requests\School;

use App\Domain\School\Enums\SubjectType;
use App\Domain\School\Models\SchoolClass;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class SaveSubjectRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Gate::allows('subjects.configure') || Gate::allows('structure.manage');
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:40'],
            'code' => ['required', 'string', 'max:20', 'regex:/^[A-Za-z0-9_-]+$/'],
            'type' => ['required', Rule::enum(SubjectType::class)],
            'classes' => ['nullable', 'array'],
            'classes.*' => ['string', Rule::exists(SchoolClass::class, 'ulid')],
        ];
    }

    public function subjectType(): SubjectType
    {
        return SubjectType::from($this->string('type')->toString());
    }

    /**
     * @return list<SchoolClass>
     */
    public function classes(): array
    {
        $ulids = array_values(array_unique(array_map('strval', (array) $this->input('classes', []))));

        return array_values(SchoolClass::query()->whereIn('ulid', $ulids)->orderBy('level')->get()->all());
    }
}
