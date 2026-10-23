<?php

namespace App\Http\Requests\School;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

class SetClassTeacherRequest extends FormRequest
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
        return [
            // Empty clears the class teacher; the Action checks the person is active staff.
            'staff' => ['nullable', 'string', 'max:26'],
        ];
    }
}
