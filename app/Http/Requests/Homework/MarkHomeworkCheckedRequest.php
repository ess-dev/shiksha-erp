<?php

namespace App\Http\Requests\Homework;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

class MarkHomeworkCheckedRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Gate::allows('homework.assign');
    }

    /**
     * `students` names who to change; leave it out to change the whole section.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'checked' => ['required', 'boolean'],
            'students' => ['nullable', 'array', 'max:200'],
            'students.*' => ['string', 'max:26'],
        ];
    }

    /**
     * @return list<string>|null
     */
    public function students(): ?array
    {
        return $this->has('students') ? array_values(array_map('strval', (array) $this->input('students'))) : null;
    }
}
