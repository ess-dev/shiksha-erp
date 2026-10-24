<?php

namespace App\Http\Requests\Exams;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

class SaveMarksRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Gate::any(['marks.enter', 'marks.reopen']);
    }

    /**
     * Each mark is checked against the exam's maximum by SaveMarks.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'marks' => ['present', 'array', 'max:500'],
            'marks.*' => ['nullable', 'string', 'max:8'],
            'publish' => ['required', 'boolean'],
        ];
    }

    /**
     * @return array<string, string|null>
     */
    public function marks(): array
    {
        $marks = [];

        foreach ((array) $this->input('marks', []) as $studentUlid => $text) {
            $marks[(string) $studentUlid] = $text === null ? null : (string) $text;
        }

        return $marks;
    }
}
