<?php

use App\Http\Controllers\Guardian\AttendanceController;
use App\Http\Controllers\Guardian\ContextController;
use App\Http\Controllers\Guardian\HomeController;
use App\Http\Controllers\Guardian\ResultsController;
use App\Http\Controllers\Guardian\SessionController;
use App\Http\Controllers\Guardian\SignInController;
use App\Http\Controllers\Guardian\SubjectsController;
use Illuminate\Support\Facades\Route;

// Parent portal: phone + one-time code on the school's own host. Included inside
// the `school` middleware group, so the school is resolved and available first.

Route::prefix('parent')->name('parent.')->group(function () {
    Route::get('sign-in', [SignInController::class, 'show'])->name('sign-in');
    Route::post('sign-in', [SignInController::class, 'sendCode'])->middleware('throttle:30,1')->name('send-code');
    Route::get('verify', [SignInController::class, 'showVerify'])->name('verify');
    Route::post('verify', [SignInController::class, 'verify'])->middleware('throttle:30,1')->name('verify.store');
    Route::post('verify/resend', [SignInController::class, 'resend'])->middleware('throttle:30,1')->name('resend-code');

    Route::middleware('parent.auth')->group(function () {
        Route::get('/', HomeController::class)->name('home');
        Route::get('attendance', AttendanceController::class)->middleware('module:attendance')->name('attendance');
        Route::get('subjects', SubjectsController::class)->name('subjects');
        Route::get('results', [ResultsController::class, 'index'])->middleware('module:exams')->name('results');
        Route::get('results/{exam}/report-card', [ResultsController::class, 'reportCard'])->middleware('module:exams')->name('results.report-card');
        Route::put('session', [SessionController::class, 'update'])->name('session.select');
        Route::post('sign-out', [SignInController::class, 'signOut'])->name('sign-out');
    });
});

Route::middleware('auth')->put('school/context', [ContextController::class, 'update'])->name('school.context.switch');
