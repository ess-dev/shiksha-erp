<?php

namespace App\Http\Requests\Leave;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

class UpdateLeaveSettingsRequest extends FormRequest
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
            'escalation_days' => ['required', 'integer', 'between:1,30'],
        ];
    }
}
