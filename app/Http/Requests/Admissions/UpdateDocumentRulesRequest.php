<?php

namespace App\Http\Requests\Admissions;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

class UpdateDocumentRulesRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Gate::allows('settings.manage');
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'rules' => ['required', 'array'],
            'rules.*.required' => ['required', 'boolean'],
            'rules.*.all_classes' => ['required', 'boolean'],
            'rules.*.classes' => ['nullable', 'array'],
            'rules.*.classes.*' => ['string', 'max:26'],
        ];
    }
}
