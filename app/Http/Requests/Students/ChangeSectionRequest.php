<?php

namespace App\Http\Requests\Students;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

class ChangeSectionRequest extends FormRequest
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
            'section' => ['required', 'string', 'max:26'],
            'reason' => ['nullable', 'string', 'max:200'],
        ];
    }
}
