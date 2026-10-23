<?php

namespace App\Http\Requests\Admissions;

use App\Domain\Admissions\Enums\AdmissionStage;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class MoveApplicationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Gate::allows('admissions.manage');
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'stage' => ['required', Rule::enum(AdmissionStage::class)],
            'accept_missing_documents' => ['nullable', 'boolean'],
        ];
    }
}
