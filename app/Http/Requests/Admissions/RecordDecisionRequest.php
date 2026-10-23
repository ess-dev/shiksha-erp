<?php

namespace App\Http\Requests\Admissions;

use App\Domain\Admissions\Enums\AdmissionDecision;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class RecordDecisionRequest extends FormRequest
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
            'decision' => ['required', Rule::enum(AdmissionDecision::class)],
        ];
    }
}
