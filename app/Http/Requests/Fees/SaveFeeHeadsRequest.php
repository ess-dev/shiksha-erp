<?php

namespace App\Http\Requests\Fees;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

class SaveFeeHeadsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Gate::allows('fee_structures.manage');
    }

    /**
     * Amounts are whole rupees per installment; the Action checks names and amounts.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'heads' => ['required', 'array', 'min:1', 'max:30'],
            'heads.*.name' => ['required', 'string', 'max:60'],
            'heads.*.amount' => ['required', 'integer', 'min:0', 'max:10000000'],
        ];
    }

    /**
     * @return list<array{name: string, amount: int}>
     */
    public function heads(): array
    {
        return array_values(array_map(
            fn ($head) => ['name' => (string) $head['name'], 'amount' => (int) $head['amount']],
            (array) $this->input('heads', []),
        ));
    }
}
