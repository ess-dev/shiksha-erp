<?php

namespace App\Tenancy;

/**
 * Builds a URL on a school's own host (<subdomain>.<base domain>), using the
 * scheme and port of the app URL. The host always comes from configuration.
 */
class SchoolUrl
{
    public static function to(Tenant $tenant, string $path = '/'): string
    {
        $app = parse_url((string) config('app.url'));

        $scheme = is_array($app) && isset($app['scheme']) ? $app['scheme'] : 'http';
        $port = is_array($app) && isset($app['port']) ? ':'.$app['port'] : '';

        return $scheme.'://'.$tenant->subdomain.'.'.config('shiksha.base_domain').$port.'/'.ltrim($path, '/');
    }
}
