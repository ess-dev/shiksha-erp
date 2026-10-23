<?php

namespace App\Http\Requests\Students;

use App\Domain\Students\Enums\TransferReason;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class IssueTransferCertificateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Gate::allows('students.issue_tc');
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'last_day' => ['required', 'date_format:Y-m-d'],
            'reason' => ['required', Rule::enum(TransferReason::class)],
        ];
    }
}
