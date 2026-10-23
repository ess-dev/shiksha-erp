<?php

use App\Http\Controllers\School\AcademicYearController;
use App\Http\Controllers\School\CampusController;
use App\Http\Controllers\School\SchoolClassController;
use App\Http\Controllers\School\SectionController;
use App\Http\Controllers\School\StructureController;
use App\Http\Controllers\School\SubjectController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth'])->prefix('school/structure')->name('school.structure.')->group(function () {
    Route::get('/', [StructureController::class, 'index'])->name('index');

    Route::post('academic-years', [AcademicYearController::class, 'store'])->name('academic-years.store');
    Route::patch('academic-years/{academicYear}/current', [AcademicYearController::class, 'makeCurrent'])->name('academic-years.current');

    Route::post('campuses', [CampusController::class, 'store'])->name('campuses.store');
    Route::patch('campuses/{campus}', [CampusController::class, 'update'])->name('campuses.update');

    Route::post('classes', [SchoolClassController::class, 'store'])->name('classes.store');
    Route::post('subjects', [SubjectController::class, 'store'])->name('subjects.store');
    Route::post('sections', [SectionController::class, 'store'])->name('sections.store');
});
