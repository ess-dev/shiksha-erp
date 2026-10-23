<?php

namespace App\Http\Requests\Students;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

class SetRollNumberRequest extends FormRequest
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
            'roll_number' => ['required', 'integer', 'min:1', 'max:9999'],
        ];
    }
}
