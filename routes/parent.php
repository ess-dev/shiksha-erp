<?php

use App\Http\Controllers\Guardian\AttendanceController;
use App\Http\Controllers\Guardian\ContextController;
use App\Http\Controllers\Guardian\FeesController;
use App\Http\Controllers\Guardian\HomeController;
use App\Http\Controllers\Guardian\HomeworkController;
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
        Route::get('fees', [FeesController::class, 'index'])->middleware('module:fees')->name('fees');
        Route::get('fees/statement', [FeesController::class, 'statement'])->middleware('module:fees')->name('fees.statement');
        Route::get('fees/receipts/{payment}', [FeesController::class, 'receipt'])->middleware('module:fees')->name('fees.receipt');
        Route::get('results', [ResultsController::class, 'index'])->middleware('module:exams')->name('results');
        Route::get('results/{exam}/report-card', [ResultsController::class, 'reportCard'])->middleware('module:exams')->name('results.report-card');
        Route::get('report-cards/{reportCard}', [ResultsController::class, 'schoolCard'])->middleware('module:exams')->name('report-cards.show');
        Route::get('report-cards/{reportCard}/file', [ResultsController::class, 'schoolCardFile'])->middleware(['module:exams', 'signed'])->name('report-cards.file');
        Route::get('homework', [HomeworkController::class, 'index'])->middleware('module:homework')->name('homework');
        Route::post('homework/{assignment}/submit', [HomeworkController::class, 'submit'])->middleware('module:homework')->name('homework.submit');
        Route::get('homework/files/{file}', [HomeworkController::class, 'attachment'])->middleware(['module:homework', 'signed'])->name('homework.files.show');
        Route::get('homework/submission-files/{file}', [HomeworkController::class, 'submission'])->middleware(['module:homework', 'signed'])->name('homework.submission-files.show');
        Route::put('session', [SessionController::class, 'update'])->name('session.select');
        Route::post('sign-out', [SignInController::class, 'signOut'])->name('sign-out');
    });
});

Route::middleware('auth')->put('school/context', [ContextController::class, 'update'])->name('school.context.switch');
