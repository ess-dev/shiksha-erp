<?php

namespace App\Http\Middleware;

use App\Domain\Platform\Enums\SuspensionReason;
use App\Tenancy\Tenant;
use App\Tenancy\TenantStatus;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\Response;

/**
 * Every school request needs a resolved school whose status allows access.
 * The page for an unknown host is neutral and never says which schools exist.
 */
class EnsureSchoolIsAvailable
{
    public function handle(Request $request, Closure $next): Response
    {
        $tenant = Tenant::current();

        if (! $tenant instanceof Tenant) {
            return $this->page($request, 'not_found', 404);
        }

        if (! $tenant->isAccessible()) {
            return match ($tenant->status) {
                TenantStatus::Suspended, TenantStatus::Expired => $this->subscriptionPage($request, $tenant),
                TenantStatus::Deleting => $this->page($request, 'not_found', 404),
                default => $this->page($request, 'unavailable', 403),
            };
        }

        return $next($request);
    }

    private function page(Request $request, string $key, int $status): Response
    {
        return Inertia::render('errors/school', [
            'title' => __("school.{$key}.title"),
            'message' => __("school.{$key}.message"),
        ])->toResponse($request)->setStatusCode($status);
    }

    /**
     * What a suspended or expired school's people see. Data is kept; only the
     * school's name and status are shown to a visitor who is not signed in.
     * The suspension reason is for signed-in people of the school, because
     * this page is reachable by anyone who knows the address.
     */
    private function subscriptionPage(Request $request, Tenant $tenant): Response
    {
        $key = $tenant->status === TenantStatus::Expired ? 'expired' : 'suspended';
        $reason = $key === 'suspended' && Auth::check()
            ? SuspensionReason::tryFrom((string) $tenant->suspended_reason)?->label()
            : null;
        $email = config('shiksha.billing_email');

        return Inertia::render('errors/subscription', [
            'status' => $key,
            'title' => __("school.{$key}.title"),
            'message' => __("school.{$key}.message", ['school' => $tenant->name]),
            'reason' => $reason === null ? null : __('school.suspended.reason', ['reason' => $reason]),
            'contact' => is_string($email) && $email !== ''
                ? ['label' => __("school.{$key}.contact"), 'href' => 'mailto:'.$email]
                : null,
        ])->toResponse($request)->setStatusCode(403);
    }
}
