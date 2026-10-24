<?php

use App\Domain\Access\Exceptions\GuardrailViolation;
use App\Domain\Admissions\Exceptions\AdmissionException;
use App\Domain\Attendance\Exceptions\AttendanceException;
use App\Domain\Exams\Exceptions\ExamException;
use App\Domain\School\Exceptions\CalendarException;
use App\Domain\School\Exceptions\HouseException;
use App\Domain\School\Exceptions\StructureException;
use App\Domain\Students\Exceptions\StudentException;
use App\Http\DomainRuleResponse;
use App\Http\Middleware\EnsureActiveMembership;
use App\Http\Middleware\EnsureGuardianContext;
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
            'parent.auth' => EnsureGuardianContext::class,
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

        // Student and house rules: 403 is a refusal, 404 a record outside the person's reach
        // (shown as not found), anything else a message on the form it came from.
        $exceptions->render(fn (StudentException $e, Request $request) => DomainRuleResponse::for($e, $request, 'student'));
        $exceptions->render(fn (HouseException $e, Request $request) => DomainRuleResponse::for($e, $request, 'house'));
        $exceptions->render(fn (CalendarException $e, Request $request) => DomainRuleResponse::for($e, $request, 'calendar'));
        $exceptions->render(fn (AttendanceException $e, Request $request) => DomainRuleResponse::for($e, $request, 'register'));
        $exceptions->render(fn (AdmissionException $e, Request $request) => DomainRuleResponse::for($e, $request, 'application'));
        $exceptions->render(fn (ExamException $e, Request $request) => DomainRuleResponse::for($e, $request, 'exam'));

        // Access guardrails (nobody grants what they do not hold, critical confirmation, last owner).
        $exceptions->render(fn (GuardrailViolation $e, Request $request) => DomainRuleResponse::for($e, $request, 'roles'));

        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );
    })->create();
