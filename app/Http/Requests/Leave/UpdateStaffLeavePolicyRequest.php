<?php

namespace App\Http\Requests\Leave;

use App\Domain\Leave\Enums\StaffLeaveType;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

class UpdateStaffLeavePolicyRequest extends FormRequest
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
        $rules = [];

        foreach (StaffLeaveType::cases() as $type) {
            $rules["policy.{$type->value}.days"] = ['nullable', 'integer', 'between:1,365'];
            $rules["policy.{$type->value}.document_after_days"] = ['nullable', 'integer', 'between:0,30'];
        }

        return $rules;
    }
}
