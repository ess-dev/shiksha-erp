<?php

namespace App\Http\Requests\Students;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

class UpdateNumberingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Gate::allows('settings.manage');
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'admission_prefix' => ['required', 'string', 'max:12', 'regex:/^[A-Za-z0-9-]+$/'],
            'tc_prefix' => ['required', 'string', 'max:12', 'regex:/^[A-Za-z0-9-]+$/'],
        ];
    }
}
