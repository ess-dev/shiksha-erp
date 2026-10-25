<?php

namespace App\Http\Requests\Fees;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

class CreateFeeGroupRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Gate::allows('fee_structures.manage');
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:40'],
            'copy_from' => ['nullable', 'string', 'max:26'],
            'classes' => ['nullable', 'array', 'max:60'],
            'classes.*' => ['required', 'string', 'max:26'],
        ];
    }
}
