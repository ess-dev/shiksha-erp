<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Base Domain
    |--------------------------------------------------------------------------
    |
    | Schools are reached at "<subdomain>.<base_domain>". Locally this is
    | "shiksha.localhost"; staging and production only change this value.
    |
    */

    'base_domain' => env('APP_BASE_DOMAIN', 'shiksha.localhost'),

    /*
    |--------------------------------------------------------------------------
    | Time Zone
    |--------------------------------------------------------------------------
    |
    | The zone calendar "today" is read in (see CalendarDate::today()). The app
    | itself stays on UTC. A per-school setting will replace this later.
    |
    */

    'timezone' => env('SHIKSHA_TIMEZONE', 'Asia/Kolkata'),

    /*
    |--------------------------------------------------------------------------
    | Platform Host
    |--------------------------------------------------------------------------
    |
    | The Super Admin area is served only on this host. Locally it is
    | "admin.shiksha.localhost". The production name is still an open decision,
    | so it is configuration. It must stay in the reserved subdomain list below
    | so no school can take it.
    |
    */

    'platform_host' => env('PLATFORM_HOST', 'admin.'.env('APP_BASE_DOMAIN', 'shiksha.localhost')),

    /*
    |--------------------------------------------------------------------------
    | Subdomain Rules
    |--------------------------------------------------------------------------
    |
    | A school subdomain is lowercase letters, digits and hyphens, not starting
    | or ending with a hyphen. Reserved names are kept for the platform.
    | illustrative - verify the reserved list before launch.
    |
    */

    'subdomain' => [
        'pattern' => '/^[a-z0-9]([a-z0-9-]{0,61}[a-z0-9])?$/',
        'min_length' => 3,
        'reserved' => [
            'www', 'app', 'api', 'admin', 'platform', 'portal', 'mail', 'smtp',
            'support', 'help', 'docs', 'status', 'static', 'cdn', 'assets',
            'staging', 'dev', 'test', 'demo', 'login', 'auth', 'billing', 'shiksha',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Onboarding
    |--------------------------------------------------------------------------
    |
    | Defaults the Super Admin onboarding wizard offers. illustrative - verify
    | the trial length, the board list and the state list with the owner.
    |
    */

    'trial_days' => (int) env('SHIKSHA_TRIAL_DAYS', 30),

    'boards' => ['CBSE', 'ICSE', 'State board', 'IB', 'Cambridge (IGCSE)', 'Pre-primary'],

    'states' => [
        'Punjab', 'Haryana', 'Chandigarh', 'Himachal Pradesh', 'Delhi', 'Uttar Pradesh',
        'Rajasthan', 'Maharashtra', 'Karnataka', 'Kerala', 'Tamil Nadu', 'Gujarat',
        'West Bengal', 'Telangana', 'Other',
    ],

    /*
    |--------------------------------------------------------------------------
    | Parent sign-in (phone + OTP)
    |--------------------------------------------------------------------------
    |
    | illustrative - verify every number below with the owner. The "log"
    | driver writes the code to the application log so the flow can be tried
    | before an SMS provider is chosen (owner decision, 2026-10-23); it refuses
    | to run in production, so a real provider must be added before launch.
    |
    */

    'otp' => [
        'driver' => env('SHIKSHA_OTP_DRIVER', 'log'),
        'length' => 6,
        'ttl_minutes' => 5,
        'resend_seconds' => 30,
        'max_attempts' => 5,
        'per_phone_per_hour' => 5,
        'per_ip_per_hour' => 20,
        'per_school_per_hour' => 300,
        'remember_days' => 30,
    ],

    /*
    |--------------------------------------------------------------------------
    | Attendance
    |--------------------------------------------------------------------------
    |
    | The percentage parents are told the school expects. illustrative - verify;
    | it becomes a school setting when the owner asks.
    |
    */

    'attendance_minimum_percent' => 75,

    /*
    | "Frequent absences" on the staff dashboard: a student absent at least
    | `absences` times in the last `school_days` school days before today.
    | illustrative - verify; it becomes a school setting when the owner asks.
    */

    'attendance_frequent_absence' => [
        'absences' => 3,
        'school_days' => 10,
    ],

    /*
    | Suggested class teacher's remarks on a report card, by the overall
    | percentage (the first band whose minimum it reaches). The teacher can
    | change every remark. Wording from the prototype; illustrative - verify.
    */

    'report_card_remarks' => [
        ['min_percent' => 90, 'text' => 'Outstanding work across subjects. Keep it up.'],
        ['min_percent' => 75, 'text' => 'Very good progress. Keep reading every day.'],
        ['min_percent' => 60, 'text' => 'Good effort. Regular revision will help further.'],
        ['min_percent' => 40, 'text' => 'Needs more practice; we will share a plan at the PTM.'],
        ['min_percent' => 0, 'text' => 'Needs support. Please meet the class teacher.'],
    ],

    /*
    |--------------------------------------------------------------------------
    | Billing contact
    |--------------------------------------------------------------------------
    |
    | Shown on the "Subscription suspended / expired" screen. Not set by
    | default: with no address the screen shows no contact button.
    |
    */

    'billing_email' => env('SHIKSHA_BILLING_EMAIL'),

];
