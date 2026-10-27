<?php

namespace App\Http\Requests\Fees;

use App\Domain\Fees\Enums\RefundMethod;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class ApproveRefundRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Gate::allows('fees.refund');
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'method' => ['required', Rule::enum(RefundMethod::class)],
            'reference' => ['nullable', 'string', 'max:60'],
            'principal_signed_off' => ['nullable', 'boolean'],
        ];
    }
}
