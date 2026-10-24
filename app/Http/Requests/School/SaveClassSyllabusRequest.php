<?php

namespace App\Http\Requests\School;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

class SaveClassSyllabusRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Gate::allows('subjects.update');
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'book' => ['nullable', 'string', 'max:80'],
            'chapters' => ['required', 'string', 'max:8000'],
            'assessment' => ['nullable', 'string', 'max:120'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return ['chapters.required' => 'Add at least one chapter.'];
    }
}
