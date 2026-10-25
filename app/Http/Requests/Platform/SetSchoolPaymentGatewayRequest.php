<?php

namespace App\Http\Requests\Platform;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class SetSchoolPaymentGatewayRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * An empty value means "no gateway". The Action checks the key against the drivers available here.
     *
     * @return array<string, array<int, ValidationRule|string>>
     */
    public function rules(): array
    {
        return [
            'gateway' => ['nullable', 'string', 'max:40'],
        ];
    }
}
