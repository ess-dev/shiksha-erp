<?php

namespace App\Http\Requests\Fees;

use App\Domain\Fees\Actions\SettleChequePayment;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class SettleChequeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Gate::allows('fees.reconcile');
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'cleared' => ['required', 'boolean'],
            'reason' => ['nullable', 'required_if:cleared,false', Rule::in(SettleChequePayment::BOUNCE_REASONS)],
        ];
    }
}
