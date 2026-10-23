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
    | Subdomain Rules
    |--------------------------------------------------------------------------
    |
    | A school subdomain is lowercase letters, digits and hyphens, not starting
    | or ending with a hyphen. Reserved names are kept for the platform.
    | illustrative — verify the reserved list before launch.
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

];
