<?php

use App\Http\Controllers\HomeController;
use Illuminate\Support\Facades\Route;

require __DIR__.'/platform.php';

Route::get('/', HomeController::class)->name('home');

Route::middleware('school')->group(function () {
    Route::middleware(['auth'])->group(function () {
        Route::inertia('dashboard', 'dashboard')->name('dashboard');
    });

    require __DIR__.'/parent.php';
    require __DIR__.'/school.php';
    require __DIR__.'/settings.php';
});
