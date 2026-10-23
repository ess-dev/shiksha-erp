<?php

namespace App\Http\Requests\School;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

class SetSubjectTeacherRequest extends FormRequest
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
        return ['staff' => ['nullable', 'string', 'max:26']];
    }
}
