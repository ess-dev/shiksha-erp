<?php

namespace App\Http\Requests\School;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

class SaveSectionProgressRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Gate::allows('subjects.update');
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'done' => ['present', 'array'],
            'done.*' => ['integer', 'min:0', 'max:200'],
            'current' => ['nullable', 'integer', 'min:0', 'max:200'],
            'periods' => ['nullable', 'integer', 'min:1', 'max:12'],
            'note' => ['nullable', 'string', 'max:300'],
        ];
    }

    /**
     * @return list<int>
     */
    public function done(): array
    {
        return array_values(array_map('intval', (array) $this->input('done', [])));
    }
}
