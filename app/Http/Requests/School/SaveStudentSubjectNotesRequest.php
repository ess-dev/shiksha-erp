<?php

namespace App\Http\Requests\School;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

class SaveStudentSubjectNotesRequest extends FormRequest
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
            'notes' => ['required', 'array', 'max:200'],
            'notes.*' => ['nullable', 'string', 'max:240'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function notes(): array
    {
        $notes = [];

        foreach ((array) $this->input('notes', []) as $studentUlid => $text) {
            $notes[(string) $studentUlid] = (string) $text;
        }

        return $notes;
    }
}
