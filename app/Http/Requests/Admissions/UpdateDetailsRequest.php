<?php

namespace App\Http\Requests\Admissions;

use App\Domain\Students\Enums\BloodGroup;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class UpdateDetailsRequest extends FormRequest
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
            'born_on' => ['nullable', 'date_format:Y-m-d'],
            'blood_group' => ['nullable', Rule::enum(BloodGroup::class)],
            'address' => ['nullable', 'string', 'max:500'],
            'house' => ['nullable', 'string', 'max:26'],
            'email' => ['nullable', 'email', 'max:120'],
        ];
    }
}
