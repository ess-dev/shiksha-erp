<?php

use App\Domain\School\Exceptions\StructureException;
use App\Http\Middleware\EnsureActiveMembership;
use App\Http\Middleware\EnsureModuleInPlan;
use App\Http\Middleware\EnsurePlatformHost;
use App\Http\Middleware\EnsurePlatformTwoStep;
use App\Http\Middleware\EnsurePlatformUser;
use App\Http\Middleware\EnsureSchoolIsAvailable;
use App\Http\Middleware\EnsureTwoStepWhenRequired;
use App\Http\Middleware\HandleAppearance;
use App\Http\Middleware\HandleInertiaRequests;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Middleware\AddLinkHeadersForPreloadedAssets;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->encryptCookies(except: ['appearance', 'sidebar_state']);

        $middleware->alias([
            'school' => EnsureSchoolIsAvailable::class,
            'module' => EnsureModuleInPlan::class,
            'platform.host' => EnsurePlatformHost::class,
            'platform.auth' => EnsurePlatformUser::class,
            'platform.two_step' => EnsurePlatformTwoStep::class,
        ]);

        $middleware->web(append: [
            EnsureActiveMembership::class,
            EnsureTwoStepWhenRequired::class,
            HandleAppearance::class,
            HandleInertiaRequests::class,
            AddLinkHeadersForPreloadedAssets::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // A structure rule the request broke is shown on the form it came from;
        // a refusal (403) is a plain forbidden response.
        $exceptions->render(function (StructureException $e, Request $request) {
            if ($e->getCode() === 403) {
                return response($e->getMessage(), 403);
            }

            return $request->expectsJson()
                ? response()->json(['message' => $e->getMessage(), 'errors' => ['structure' => [$e->getMessage()]]], 422)
                : back()->withErrors(['structure' => $e->getMessage()]);
        });

        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );
    })->create();
