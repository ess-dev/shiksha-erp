<?php

namespace App\Http\Requests\Platform;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdatePlanRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, ValidationRule|string>>
     */
    public function rules(): array
    {
        return [
            // Entered in whole rupees; stored as paise.
            'price' => ['required', 'integer', 'min:1', 'max:1000000'],
            'min_students' => ['required', 'integer', 'min:0', 'max:1000000'],
            'modules' => ['present', 'array'],
            'modules.*' => ['string', 'distinct', Rule::exists('landlord.platform_modules', 'key')],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'price.required' => 'Enter a price above zero.',
            'price.integer' => 'Enter a whole number of rupees.',
            'price.min' => 'Enter a price above zero.',
            'min_students.required' => 'Enter zero or more.',
            'min_students.integer' => 'Enter a whole number.',
            'min_students.min' => 'Enter zero or more.',
        ];
    }

    public function pricePaise(): int
    {
        return $this->integer('price') * 100;
    }

    /**
     * @return list<string>
     */
    public function moduleKeys(): array
    {
        /** @var list<string> $keys */
        $keys = array_values($this->input('modules', []));

        return $keys;
    }
}
