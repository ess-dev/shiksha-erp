<?php

namespace App\Http\Requests\Fees;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

class SendRemindersRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Gate::allows('fees.remind');
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'invoices' => ['required', 'array', 'min:1', 'max:500'],
            'invoices.*' => ['required', 'string', 'max:26'],
        ];
    }

    /**
     * @return list<string>
     */
    public function invoiceUlids(): array
    {
        return array_values(array_map(strval(...), (array) $this->input('invoices', [])));
    }
}
