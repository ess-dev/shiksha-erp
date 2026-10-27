<?php

namespace App\Http\Requests\Fees;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

class RequestRefundRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Gate::allows('fees.refund');
    }

    /**
     * The amount is in whole rupees.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'student' => ['required', 'string', 'max:26'],
            'payment' => ['nullable', 'string', 'max:26'],
            'amount' => ['required', 'integer', 'min:1', 'max:100000000'],
            'reason' => ['required', 'string', 'min:5', 'max:300'],
        ];
    }
}
