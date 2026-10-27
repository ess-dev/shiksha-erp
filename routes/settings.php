<?php

use App\Http\Controllers\Settings\ProfileController;
use App\Http\Controllers\Settings\SecurityController;
use Illuminate\Auth\Middleware\RequirePassword;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth'])->group(function () {
    Route::redirect('settings', '/settings/profile');

    Route::get('settings/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('settings/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::patch('settings/profile/channels', [ProfileController::class, 'channels'])->name('profile.channels');
    Route::get('settings/profile/photo', [ProfileController::class, 'photo'])->name('profile.photo');
    Route::post('settings/profile/photo', [ProfileController::class, 'storePhoto'])->name('profile.photo.store');
    Route::delete('settings/profile/photo', [ProfileController::class, 'destroyPhoto'])->name('profile.photo.destroy');
});

Route::middleware(['auth'])->group(function () {
    Route::get('settings/security', [SecurityController::class, 'edit'])
        ->middleware(RequirePassword::class)
        ->name('security.edit');

    Route::put('settings/password', [SecurityController::class, 'update'])
        ->middleware('throttle:6,1')
        ->name('user-password.update');

    Route::inertia('settings/appearance', 'settings/appearance')->name('appearance.edit');
});
