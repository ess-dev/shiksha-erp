<?php

use Illuminate\Support\Facades\Route;

Route::middleware('school')->group(function () {
    Route::redirect('/', '/dashboard')->name('home');

    Route::middleware(['auth'])->group(function () {
        Route::inertia('dashboard', 'dashboard')->name('dashboard');
    });

    require __DIR__.'/settings.php';
});
