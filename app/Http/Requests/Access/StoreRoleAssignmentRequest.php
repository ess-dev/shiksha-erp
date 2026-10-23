<?php

namespace App\Http\Requests\Access;

use App\Domain\Access\Enums\ScopeType;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class StoreRoleAssignmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Gate::allows('roles.assign') || Gate::allows('roles.manage');
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $tenant = (string) config('multitenancy.tenant_database_connection_name');
        $type = $this->input('scope_type');

        return [
            'scope_type' => ['required', Rule::enum(ScopeType::class)],
            'campuses' => [Rule::requiredIf(in_array($type, ['campus', 'wing'], true)), 'array', 'max:50'],
            'campuses.*' => ['string', Rule::exists($tenant.'.campuses', 'ulid')],
            'sections' => [Rule::requiredIf($type === 'sections'), 'array', 'max:500'],
            'sections.*' => ['string', Rule::exists($tenant.'.sections', 'ulid')],
            'from_level' => [Rule::requiredIf($type === 'wing'), 'nullable', 'integer', 'min:-5', 'max:20'],
            'to_level' => [Rule::requiredIf($type === 'wing'), 'nullable', 'integer', 'min:-5', 'max:20', 'gte:from_level'],
            'confirm_critical' => ['nullable', 'boolean'],
        ];
    }

    /**
     * An empty campus/section list is a mistake, not a wider scope.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'campuses.required' => 'Choose at least one campus.',
            'sections.required' => 'Choose at least one section.',
        ];
    }
}
