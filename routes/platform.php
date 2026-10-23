<?php

use App\Http\Controllers\Platform\DashboardController;
use App\Http\Controllers\Platform\PlanController;
use App\Http\Controllers\Platform\SignInController;
use App\Http\Controllers\Platform\TwoStepChallengeController;
use App\Http\Controllers\Platform\TwoStepSetupController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Super Admin (platform host only)
|--------------------------------------------------------------------------
|
| Not bound to a route domain: the platform.host middleware 404s every other
| host, which keeps generated frontend URLs path-only. Paths must not clash
| with school or Fortify paths ("/dashboard", "/login", "/user/...",
| "/two-factor-challenge"), because both sets share one host-less route table.
|
*/

Route::middleware('platform.host')->name('platform.')->group(function () {
    Route::get('sign-in', [SignInController::class, 'create'])->name('sign-in');
    Route::post('sign-in', [SignInController::class, 'store'])->middleware('throttle:platform-sign-in')->name('sign-in.store');

    Route::middleware('platform.auth')->group(function () {
        Route::post('sign-out', [SignInController::class, 'destroy'])->name('sign-out');

        Route::prefix('two-step')->name('two-step.')->group(function () {
            Route::get('challenge', [TwoStepChallengeController::class, 'create'])->name('challenge');
            Route::post('challenge', [TwoStepChallengeController::class, 'store'])->middleware('throttle:platform-two-step')->name('challenge.store');

            Route::get('setup', [TwoStepSetupController::class, 'show'])->name('setup');
            Route::post('setup', [TwoStepSetupController::class, 'begin'])->name('setup.begin');
            Route::post('setup/confirm', [TwoStepSetupController::class, 'confirm'])->middleware('throttle:platform-two-step')->name('setup.confirm');
            Route::get('recovery-codes', [TwoStepSetupController::class, 'recoveryCodes'])->name('recovery-codes');
        });

        Route::middleware('platform.two_step')->group(function () {
            Route::get('overview', [DashboardController::class, 'index'])->name('dashboard');

            Route::get('plans', [PlanController::class, 'index'])->name('plans.index');
            Route::patch('plans/{plan}', [PlanController::class, 'update'])->name('plans.update');
        });
    });
});
