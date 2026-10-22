<?php

namespace App\Http\Requests\Students;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

class ReorderHousesRequest extends FormRequest
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
            'order' => ['required', 'array', 'min:1', 'max:100'],
            'order.*' => ['required', 'string', 'max:26'],
        ];
    }
}
