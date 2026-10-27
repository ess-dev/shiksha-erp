<?php

namespace App\Http\Requests\Leave;

use App\Domain\Leave\Enums\StaffLeaveType;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ApplyForStaffLeaveRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Dates, balances and the certificate rule are the Action's.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'leave_type' => ['required', Rule::enum(StaffLeaveType::class)],
            'half_day' => ['sometimes', 'boolean'],
            'from_on' => ['required', 'date_format:Y-m-d'],
            'to_on' => ['nullable', 'date_format:Y-m-d'],
            'reason' => ['required', 'string', 'max:1000'],
            'suggested_cover' => ['nullable', 'string', 'max:80'],
            'attachment' => ['nullable', 'file'],
        ];
    }
}
