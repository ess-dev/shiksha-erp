<?php

namespace App\Http\Requests\Exams;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

class DecideReportCardsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Gate::allows('reportcards.approve');
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'section' => ['required', 'string', 'max:26'],
            'approve' => ['required', 'boolean'],
            'note' => ['nullable', 'string', 'max:200'],
            'students' => ['nullable', 'array', 'max:200'],
            'students.*' => ['string', 'max:26'],
        ];
    }

    /**
     * @return list<string>|null
     */
    public function students(): ?array
    {
        $students = $this->input('students');

        return is_array($students) ? array_values(array_map(strval(...), $students)) : null;
    }
}
