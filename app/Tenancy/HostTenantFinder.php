<?php

namespace App\Tenancy;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Spatie\Multitenancy\Contracts\IsTenant;
use Spatie\Multitenancy\TenantFinder\TenantFinder;

/**
 * Resolves the school from the request host only: a verified custom domain
 * first, then "<subdomain>.<base domain>". Never reads client-supplied IDs.
 */
class HostTenantFinder extends TenantFinder
{
    public function findForRequest(Request $request): ?IsTenant
    {
        $host = strtolower($request->getHost());

        $tenantId = DB::connection(config('multitenancy.landlord_database_connection_name'))
            ->table('tenant_domains')
            ->where('domain', $host)
            ->whereNotNull('verified_at')
            ->value('tenant_id');

        if ($tenantId !== null) {
            return Tenant::query()->whereKey($tenantId)->first();
        }

        $suffix = '.'.strtolower((string) config('shiksha.base_domain'));

        if (! str_ends_with($host, $suffix)) {
            return null;
        }

        $subdomain = substr($host, 0, -strlen($suffix));

        if ($subdomain === '' || str_contains($subdomain, '.')) {
            return null;
        }

        return Tenant::query()->where('subdomain', $subdomain)->first();
    }
}
