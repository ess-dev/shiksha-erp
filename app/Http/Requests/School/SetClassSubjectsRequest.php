<?php

namespace App\Http\Requests\School;

use App\Domain\School\Models\Subject;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class SetClassSubjectsRequest extends FormRequest
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
            'subjects' => ['required', 'array', 'min:1'],
            'subjects.*' => ['string', Rule::exists(Subject::class, 'ulid')],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'subjects.required' => 'Pick at least one examined subject.',
            'subjects.min' => 'Pick at least one examined subject.',
        ];
    }

    /**
     * @return list<Subject>
     */
    public function subjects(): array
    {
        $ulids = array_values(array_unique(array_map('strval', (array) $this->input('subjects', []))));

        return array_values(Subject::query()->whereIn('ulid', $ulids)->orderBy('id')->get()->all());
    }
}
