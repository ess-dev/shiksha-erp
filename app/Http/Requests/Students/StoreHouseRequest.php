<?php

namespace App\Http\Requests\Students;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

class StoreHouseRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Gate::allows('houses.manage');
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:60'],
        ];
    }
}
