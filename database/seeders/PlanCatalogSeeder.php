<?php

namespace Database\Seeders;

use App\Domain\Platform\Models\Plan;
use App\Domain\Platform\Models\PlanModule;
use App\Domain\Platform\Models\PlatformModule;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

/**
 * Starting modules and plans, from the prototype. Names, prices (paise per
 * student per year) and minimums are illustrative — verify before launch.
 *
 * Only creates what is missing: a plan the Super Admin has edited is never
 * overwritten, so this is safe to run again.
 */
class PlanCatalogSeeder extends Seeder
{
    /** @var array<string, string> key => label, in display order */
    private const MODULES = [
        'admissions' => 'Admissions',
        'attendance' => 'Attendance & timetable',
        'fees' => 'Fees & finance',
        'exams' => 'Exams & report cards',
        'communication' => 'Notices & messaging',
        'homework' => 'Homework',
        'reports' => 'Reports & analytics',
        'multi_campus' => 'Multi-campus',
        'whatsapp' => 'WhatsApp channel',
        'gallery' => 'Gallery & greetings',
        'appointments' => 'Appointments',
        'transport' => 'GPS bus tracking',
        'library' => 'Library',
        'payroll' => 'HR & payroll',
    ];

    /** illustrative — verify */
    private const PLANS = [
        'starter' => [
            'name' => 'Starter',
            'description' => 'Core records, attendance and fees for single-campus schools.',
            'badge' => null,
            'price_paise' => 45000,
            'min_students' => 100,
            'modules' => ['attendance', 'fees', 'communication'],
        ],
        'growth' => [
            'name' => 'Growth',
            'description' => 'Everything a growing school needs, including admissions and exams.',
            'badge' => 'Most popular',
            'price_paise' => 75000,
            'min_students' => 200,
            'modules' => ['admissions', 'attendance', 'fees', 'exams', 'communication', 'homework', 'reports', 'multi_campus', 'gallery', 'appointments', 'payroll'],
        ],
        'premium' => [
            'name' => 'Premium',
            'description' => 'All modules plus WhatsApp and priority support.',
            'badge' => null,
            'price_paise' => 110000,
            'min_students' => 300,
            'modules' => ['admissions', 'attendance', 'fees', 'exams', 'communication', 'homework', 'reports', 'multi_campus', 'whatsapp', 'gallery', 'appointments', 'transport', 'payroll'],
        ],
    ];

    public function run(): void
    {
        $order = 0;

        foreach (self::MODULES as $key => $label) {
            PlatformModule::query()->firstOrCreate(['key' => $key], ['label' => $label, 'sort_order' => ++$order]);
        }

        $order = 0;

        foreach (self::PLANS as $key => $definition) {
            $order++;

            if (Plan::query()->where('key', $key)->exists()) {
                continue;
            }

            $plan = Plan::query()->create([
                'ulid' => strtolower((string) Str::ulid()),
                'key' => $key,
                'name' => $definition['name'],
                'description' => $definition['description'],
                'badge' => $definition['badge'],
                'price_per_student_year_paise' => $definition['price_paise'],
                'min_students' => $definition['min_students'],
                'sort_order' => $order,
            ]);

            foreach ($definition['modules'] as $moduleKey) {
                PlanModule::query()->create(['plan_id' => $plan->id, 'module_key' => $moduleKey]);
            }
        }
    }
}
