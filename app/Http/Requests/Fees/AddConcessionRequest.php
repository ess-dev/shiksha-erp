<?php

namespace App\Http\Requests\Fees;

use App\Domain\Fees\Enums\ConcessionType;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class AddConcessionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Gate::allows('fee_structures.manage');
    }

    /**
     * `value` is a whole percent, or whole rupees for a fixed amount.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:60'],
            'type' => ['required', Rule::in(array_map(fn (ConcessionType $type) => $type->value, ConcessionType::cases()))],
            'value' => ['required', 'integer', 'min:1', 'max:10000000'],
            'head' => ['required', 'string', 'max:60'],
        ];
    }
}
