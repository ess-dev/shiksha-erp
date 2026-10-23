<?php

namespace App\Http\Requests\Platform;

use App\Domain\Access\Actions\SetUpSchoolOwner;
use App\Domain\Access\Queries\StaffRoleOptionsQuery;
use App\Domain\Platform\Actions\OnboardSchool;
use App\Domain\Platform\Data\OnboardSchoolData;
use App\Domain\Platform\Models\Plan;
use App\Domain\Platform\Models\PlanModule;
use App\Tenancy\SubdomainRules;
use App\Tenancy\Tenant;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class OnboardSchoolRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $subdomain = $this->input('org.subdomain');

        if (is_string($subdomain)) {
            $this->merge(['org' => [...(array) $this->input('org'), 'subdomain' => strtolower(trim($subdomain))]]);
        }
    }

    /**
     * @return array<string, array<int, ValidationRule|Closure|string>>
     */
    public function rules(): array
    {
        $roleKeys = [...array_map(fn (array $role) => $role['key'], app(StaffRoleOptionsQuery::class)->templates()), OnboardSchool::TEACHER_ROLE];

        return [
            'org.name' => ['required', 'string', 'min:3', 'max:120', function (string $attribute, mixed $value, Closure $fail) {
                if (Tenant::query()->whereRaw('lower(name) = ?', [mb_strtolower((string) $value)])->exists()) {
                    $fail('A school with this name is already on the platform.');
                }
            }],
            'org.subdomain' => ['required', 'string', function (string $attribute, mixed $value, Closure $fail) {
                if (! SubdomainRules::isValid((string) $value)) {
                    $fail('Use 3 or more lowercase letters, digits or hyphens, and not a reserved name.');
                } elseif (Tenant::query()->where('subdomain', $value)->exists()) {
                    $fail('This web address is taken.');
                }
            }],
            'org.board' => ['required', Rule::in((array) config('shiksha.boards'))],
            'org.city' => ['required', 'string', 'min:2', 'max:80'],
            'org.state' => ['required', Rule::in((array) config('shiksha.states'))],
            'org.contact_name' => ['required', 'string', 'min:3', 'max:120'],
            'org.contact_phone' => ['required', 'regex:/^[6-9]\d{9}$/'],
            'org.contact_email' => ['required', 'email:rfc', 'max:190'],

            'subscription.plan' => ['required', 'string', Rule::exists('landlord.plans', 'ulid')],
            'subscription.start' => ['required', Rule::in(['trial', 'paid'])],

            'campuses' => ['required', 'array', 'min:1', 'max:20'],
            'campuses.*.name' => ['required', 'string', 'min:2', 'max:80'],
            'campuses.*.address' => ['nullable', 'string', 'max:200'],

            'academic_year.name' => ['required', 'string', 'max:20'],
            'academic_year.starts_on' => ['required', 'date_format:Y-m-d'],
            'academic_year.ends_on' => ['required', 'date_format:Y-m-d', 'after:academic_year.starts_on'],

            'classes' => ['present', 'array', 'max:20'],
            'classes.*.level' => ['required', 'integer', 'between:0,12', 'distinct'],
            'classes.*.sections' => ['required', 'array', 'min:1', 'max:12'],
            'classes.*.sections.*' => ['required', 'string', 'regex:/^[A-Z0-9]{1,3}$/'],

            'staff' => ['required', 'array', 'min:1', 'max:30'],
            'staff.*.name' => ['required', 'string', 'min:2', 'max:120'],
            'staff.*.email' => ['required', 'email:rfc', 'max:190', 'distinct:ignore_case'],
            'staff.*.role' => ['required', Rule::in($roleKeys)],

            'modules' => ['required', 'array', 'min:1'],
            'modules.*' => ['string', 'distinct', Rule::exists('landlord.platform_modules', 'key')],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'org.name.required' => 'Enter the school’s name.',
            'org.city.required' => 'Enter the city.',
            'org.contact_name.required' => 'Enter a contact person.',
            'org.contact_phone.regex' => 'Enter a 10-digit Indian mobile number.',
            'org.contact_email.email' => 'Enter a valid email.',
            'academic_year.ends_on.after' => 'The end must be after the start.',
            'classes.*.sections.*.regex' => 'Use short names like A, B, C.',
            'staff.*.email.distinct' => 'Each person needs a different email.',
            'modules.min' => 'Turn on at least one module.',
        ];
    }

    /**
     * @return list<Closure(Validator): void>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                if ($validator->errors()->isNotEmpty()) {
                    return;
                }

                foreach ((array) $this->input('classes') as $i => $class) {
                    $seen = [];

                    foreach ((array) ($class['sections'] ?? []) as $j => $section) {
                        if (in_array($section, $seen, true)) {
                            $validator->errors()->add("classes.{$i}.sections.{$j}", 'A section is listed twice.');
                        }

                        $seen[] = $section;
                    }
                }

                if ($validator->errors()->isNotEmpty()) {
                    return;
                }

                $roles = array_column((array) $this->input('staff'), 'role');

                if (! in_array(SetUpSchoolOwner::OWNER_TEMPLATE, $roles, true)) {
                    $validator->errors()->add('staff', 'Invite at least one Principal (the school owner).');
                }

                $plan = Plan::query()->where('ulid', (string) $this->input('subscription.plan'))->first();
                $included = $plan === null ? [] : $plan->planModules()->get()->map(fn (PlanModule $row) => $row->module_key)->all();

                if (array_diff((array) $this->input('modules'), $included) !== []) {
                    $validator->errors()->add('modules', 'Only modules in the chosen plan can be switched on here.');
                }
            },
        ];
    }

    public function toData(): OnboardSchoolData
    {
        /** @var array<string, mixed> $v */
        $v = $this->validated();

        $classes = [];

        foreach ((array) $v['classes'] as $class) {
            $level = (int) $class['level'];

            $classes[] = [
                'level' => $level,
                'name' => $level === 0 ? 'Pre-primary' : "Class {$level}",
                'sections' => array_values($class['sections']),
            ];
        }

        usort($classes, fn (array $a, array $b) => $a['level'] <=> $b['level']);

        return new OnboardSchoolData(
            name: trim((string) $v['org']['name']),
            subdomain: (string) $v['org']['subdomain'],
            board: (string) $v['org']['board'],
            city: trim((string) $v['org']['city']),
            state: (string) $v['org']['state'],
            contactName: trim((string) $v['org']['contact_name']),
            contactPhone: (string) $v['org']['contact_phone'],
            contactEmail: trim((string) $v['org']['contact_email']),
            planUlid: (string) $v['subscription']['plan'],
            start: $v['subscription']['start'] === 'paid' ? 'paid' : 'trial',
            campuses: array_values(array_map(fn (array $campus) => [
                'name' => trim((string) $campus['name']),
                'address' => isset($campus['address']) && trim((string) $campus['address']) !== '' ? trim((string) $campus['address']) : null,
            ], (array) $v['campuses'])),
            academicYear: [
                'name' => trim((string) $v['academic_year']['name']),
                'starts_on' => (string) $v['academic_year']['starts_on'],
                'ends_on' => (string) $v['academic_year']['ends_on'],
            ],
            classes: $classes,
            staff: array_values(array_map(fn (array $person) => [
                'name' => trim((string) $person['name']),
                'email' => trim((string) $person['email']),
                'role' => (string) $person['role'],
            ], (array) $v['staff'])),
            moduleKeys: array_values((array) $v['modules']),
        );
    }
}
