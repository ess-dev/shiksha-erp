<?php

namespace App\Http\Requests\Admissions;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

class EnrolApplicantRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Gate::allows('admissions.enroll');
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'section' => ['required', 'string', 'max:26'],
        ];
    }
}
