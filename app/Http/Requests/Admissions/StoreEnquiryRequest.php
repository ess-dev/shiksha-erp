<?php

namespace App\Http\Requests\Admissions;

use App\Domain\Admissions\Enums\AdmissionSource;
use App\Domain\Students\Enums\Gender;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class StoreEnquiryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Gate::allows('admissions.manage');
    }

    /**
     * Shape only; the Action enforces the rules (campus runs the class, phone, age).
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'first_name' => ['required', 'string', 'min:2', 'max:60'],
            'last_name' => ['required', 'string', 'min:2', 'max:60'],
            'gender' => ['required', Rule::enum(Gender::class)],
            'born_on' => ['nullable', 'date_format:Y-m-d'],
            'class' => ['required', 'string', 'max:26'],
            'campus' => ['required', 'string', 'max:26'],
            'parent_name' => ['required', 'string', 'min:3', 'max:100'],
            'relation' => ['required', Rule::in(['Mother', 'Father', 'Guardian'])],
            'phone' => ['required', 'string', 'max:20'],
            'source' => ['required', Rule::enum(AdmissionSource::class)],
            'notes' => ['nullable', 'string', 'max:300'],
        ];
    }
}
