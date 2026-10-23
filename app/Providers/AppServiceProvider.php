<?php

namespace App\Providers;

use App\Domain\Access\Authorizer;
use App\Domain\Access\Data\ScopeTarget;
use App\Domain\Audit\Contracts\ResolvesAuditActor;
use App\Domain\Audit\SessionAuditActor;
use App\Domain\Identity\Models\Identity;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(ResolvesAuditActor::class, SessionAuditActor::class);
        $this->app->singleton(Authorizer::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->configureDefaults();
        $this->configureAccessGate();

        // `php artisan migrate` runs landlord migrations only; tenant migrations
        // run per school via `tenants:artisan`.
        $this->loadMigrationsFrom(database_path('migrations/landlord'));
    }

    /**
     * Permission keys ("fees.refund") are answered by the Authorizer: tenant
     * available, module in plan, permission held, scope covers the optional
     * ScopeTarget argument. Anything not shaped like a permission key falls
     * through to ordinary Gates and Policies.
     */
    protected function configureAccessGate(): void
    {
        Gate::before(function ($user, string $ability, array $arguments) {
            if (! $user instanceof Identity || preg_match('/^[a-z_]+\.[a-z_]+$/', $ability) !== 1) {
                return null;
            }

            $target = $arguments[0] ?? null;

            return app(Authorizer::class)->allows($user, $ability, $target instanceof ScopeTarget ? $target : null);
        });
    }

    /**
     * Configure default behaviors for production-ready applications.
     */
    protected function configureDefaults(): void
    {
        Date::use(CarbonImmutable::class);

        DB::prohibitDestructiveCommands(
            app()->isProduction(),
        );

        Password::defaults(fn (): ?Password => app()->isProduction()
            ? Password::min(12)
                ->mixedCase()
                ->letters()
                ->numbers()
                ->symbols()
                ->uncompromised()
            : null,
        );
    }
}
