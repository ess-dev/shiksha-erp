<?php

namespace App\Http\Requests\Leave;

use App\Domain\Leave\Enums\LeaveReason;
use App\Domain\Leave\LeaveAttachmentStore;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreLeaveRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * The parent portal checks the child, the dates and the rules in the Action;
     * the file type is checked there from the content.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'child' => ['nullable', 'string', 'max:26'],
            'from_on' => ['required', 'date_format:Y-m-d'],
            'to_on' => ['required', 'date_format:Y-m-d'],
            'reason_type' => ['required', Rule::enum(LeaveReason::class)],
            'reason' => ['required', 'string', 'max:1000'],
            'attachment' => ['nullable', 'file', 'max:'.(LeaveAttachmentStore::MAX_BYTES / 1024)],
        ];
    }
}
