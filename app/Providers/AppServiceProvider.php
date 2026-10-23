<?php

namespace App\Providers;

use App\Domain\Access\Authorizer;
use App\Domain\Access\Data\ScopeTarget;
use App\Domain\Attendance\Contracts\AbsenceNotifier;
use App\Domain\Attendance\LogAbsenceNotifier;
use App\Domain\Audit\Contracts\ResolvesAuditActor;
use App\Domain\Audit\SessionAuditActor;
use App\Domain\Identity\Authentication\LogOtpSender;
use App\Domain\Identity\Authentication\MailOtpSender;
use App\Domain\Identity\Contracts\OtpSender;
use App\Domain\Identity\Models\Identity;
use Carbon\CarbonImmutable;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;
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
        $this->app->bind(AbsenceNotifier::class, LogAbsenceNotifier::class);
        // The SMS provider is an open decision: only the development drivers (log, mail) exist until one is chosen.
        $this->app->bind(OtpSender::class, fn () => match (config('shiksha.otp.driver')) {
            'mail' => $this->app->make(MailOtpSender::class),
            default => $this->app->make(LogOtpSender::class),
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->configureDefaults();
        $this->configureAccessGate();
        $this->configurePlatformRateLimiting();

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
     * Super Admin sign-in is limited per email and IP; two-step attempts per
     * signed-in user (and IP), so a stolen password cannot be used to guess codes.
     */
    protected function configurePlatformRateLimiting(): void
    {
        RateLimiter::for('platform-sign-in', fn (Request $request) => Limit::perMinute(5)->by(
            Str::lower((string) $request->input('email')).'|'.$request->ip(),
        ));

        RateLimiter::for('platform-two-step', fn (Request $request) => Limit::perMinute(5)->by(
            ($request->user('platform')?->getAuthIdentifier() ?? 'guest').'|'.$request->ip(),
        ));
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
