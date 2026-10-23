<?php

use App\Http\Controllers\Access\RoleAssignmentController;
use App\Http\Controllers\Access\RoleController;
use App\Http\Controllers\Admissions\AdmissionController;
use App\Http\Controllers\Admissions\AdmissionSettingsController;
use App\Http\Controllers\Admissions\ApplicationController;
use App\Http\Controllers\Admissions\ApplicationDocumentController;
use App\Http\Controllers\Attendance\AttendanceController;
use App\Http\Controllers\Attendance\AttendanceSettingsController;
use App\Http\Controllers\Attendance\HolidayController;
use App\Http\Controllers\School\AcademicYearController;
use App\Http\Controllers\School\CampusController;
use App\Http\Controllers\School\CampusSelectionController;
use App\Http\Controllers\School\ClassTeacherController;
use App\Http\Controllers\School\SchoolClassController;
use App\Http\Controllers\School\SectionController;
use App\Http\Controllers\School\SessionSelectionController;
use App\Http\Controllers\School\StructureController;
use App\Http\Controllers\School\SubjectController;
use App\Http\Controllers\Students\HouseController;
use App\Http\Controllers\Students\SiblingController;
use App\Http\Controllers\Students\StudentController;
use App\Http\Controllers\Students\StudentDocumentController;
use App\Http\Controllers\Students\StudentExportController;
use App\Http\Controllers\Students\StudentImportController;
use App\Http\Controllers\Students\StudentSectionController;
use App\Http\Controllers\Students\StudentSettingsController;
use App\Http\Controllers\Students\TransferCertificateController;
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
    Route::put('sections/{section}/class-teacher', [ClassTeacherController::class, 'update'])->name('sections.class-teacher');
});

Route::middleware(['auth'])->prefix('school/roles')->name('school.roles.')->group(function () {
    Route::get('/', [RoleController::class, 'index'])->name('index');
    Route::post('/', [RoleController::class, 'store'])->name('store');
    Route::put('{role}/permissions', [RoleController::class, 'update'])->name('permissions');
    Route::post('{role}/template-update', [RoleController::class, 'applyUpdate'])->name('template-update');

    Route::post('{role}/staff/{staff}', [RoleAssignmentController::class, 'store'])->name('assign');
    Route::delete('assignments/{assignment}', [RoleAssignmentController::class, 'destroy'])->name('assignments.destroy');
});

Route::middleware(['auth'])->group(function () {
    Route::put('school/campus', [CampusSelectionController::class, 'update'])->name('school.campus.select');
    Route::put('school/session', [SessionSelectionController::class, 'update'])->name('school.session.select');

    Route::prefix('students')->name('students.')->group(function () {
        Route::get('/', [StudentController::class, 'index'])->name('index');
        Route::get('export', StudentExportController::class)->name('export');

        Route::get('import/template', [StudentImportController::class, 'template'])->name('import.template');
        Route::post('import/preview', [StudentImportController::class, 'preview'])->name('import.preview');
        Route::post('import', [StudentImportController::class, 'store'])->name('import.store');

        Route::get('settings', [StudentSettingsController::class, 'show'])->name('settings');
        Route::put('settings/numbering', [StudentSettingsController::class, 'updateNumbering'])->name('settings.numbering');
        Route::post('settings/houses', [HouseController::class, 'store'])->name('houses.store');
        Route::put('settings/houses/order', [HouseController::class, 'reorder'])->name('houses.reorder');
        Route::patch('settings/houses/{house}', [HouseController::class, 'update'])->name('houses.update');

        Route::post('documents/{document}/verify', [StudentDocumentController::class, 'verify'])->name('documents.verify');
        Route::get('documents/{document}', [StudentDocumentController::class, 'show'])->middleware('signed')->name('documents.show');

        Route::get('{student}', [StudentController::class, 'show'])->name('show');
        Route::patch('{student}', [StudentController::class, 'update'])->name('update');
        Route::post('{student}/section', [StudentSectionController::class, 'store'])->name('section');
        Route::patch('{student}/roll-number', [StudentSectionController::class, 'updateRoll'])->name('roll-number');
        Route::post('{student}/transfer-certificate', [TransferCertificateController::class, 'store'])->name('tc.store');
        Route::get('{student}/transfer-certificate', [TransferCertificateController::class, 'show'])->name('tc.show');
        Route::get('{student}/sibling-candidates', [SiblingController::class, 'candidates'])->name('siblings.candidates');
        Route::post('{student}/siblings', [SiblingController::class, 'store'])->name('siblings.store');
        Route::delete('{student}/siblings/{sibling}', [SiblingController::class, 'destroy'])->name('siblings.destroy');
        Route::post('{student}/documents', [StudentDocumentController::class, 'store'])->name('documents.store');
    });
});

Route::middleware(['auth', 'module:admissions'])->prefix('admissions')->name('admissions.')->group(function () {
    Route::get('/', [AdmissionController::class, 'index'])->name('index');
    Route::post('/', [AdmissionController::class, 'store'])->name('store');

    Route::get('settings', [AdmissionSettingsController::class, 'show'])->name('settings');
    Route::put('settings/documents', [AdmissionSettingsController::class, 'update'])->name('settings.documents');

    Route::post('{application}/move', [ApplicationController::class, 'move'])->name('move');
    Route::put('{application}/details', [ApplicationController::class, 'details'])->name('details');
    Route::put('{application}/notes', [ApplicationController::class, 'notes'])->name('notes');
    Route::post('{application}/decision', [ApplicationController::class, 'decide'])->name('decision');
    Route::post('{application}/enrol', [ApplicationController::class, 'enrol'])->name('enrol');

    Route::put('{application}/documents/{kind}', [ApplicationDocumentController::class, 'received'])->name('documents.received');
    Route::post('{application}/documents/{kind}', [ApplicationDocumentController::class, 'store'])->name('documents.store');
    Route::get('{application}/documents/{kind}', [ApplicationDocumentController::class, 'show'])->middleware('signed')->name('documents.show');
});

Route::middleware(['auth', 'module:attendance'])->prefix('attendance')->name('attendance.')->group(function () {
    Route::get('/', [AttendanceController::class, 'index'])->name('index');
    Route::post('/', [AttendanceController::class, 'store'])->name('store');

    Route::get('settings', [AttendanceSettingsController::class, 'show'])->name('settings');
    Route::put('settings', [AttendanceSettingsController::class, 'update'])->name('settings.update');
    Route::post('settings/holidays', [HolidayController::class, 'store'])->name('holidays.store');
    Route::delete('settings/holidays/{holiday}', [HolidayController::class, 'destroy'])->name('holidays.destroy');
});
