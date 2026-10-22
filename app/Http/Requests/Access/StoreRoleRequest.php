<?php

namespace App\Http\Requests\Access;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

class StoreRoleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Gate::allows('roles.manage');
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:80'],
            'template' => ['nullable', 'string', 'max:60'],
            'permissions' => ['nullable', 'array', 'max:200'],
            'permissions.*' => ['string', 'max:80'],
            'confirm_critical' => ['nullable', 'boolean'],
        ];
    }
}
