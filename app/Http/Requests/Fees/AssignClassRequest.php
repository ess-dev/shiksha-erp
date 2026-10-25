<?php

namespace App\Http\Requests\Fees;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

class AssignClassRequest extends FormRequest
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
        return ['group' => ['required', 'string', 'max:26']];
    }
}
