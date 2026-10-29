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
use App\Http\Controllers\Exams\ExamController;
use App\Http\Controllers\Exams\ExamStatusController;
use App\Http\Controllers\Exams\GradeScaleController;
use App\Http\Controllers\Exams\MarksController;
use App\Http\Controllers\Exams\ReportCardController;
use App\Http\Controllers\Fees\CollectController;
use App\Http\Controllers\Fees\DuesController;
use App\Http\Controllers\Fees\FeeStructureController;
use App\Http\Controllers\Fees\PaymentController;
use App\Http\Controllers\Fees\ReceiptController;
use App\Http\Controllers\Fees\RefundController;
use App\Http\Controllers\Homework\HomeworkController;
use App\Http\Controllers\Homework\HomeworkFileController;
use App\Http\Controllers\Leave\LeaveController;
use App\Http\Controllers\Leave\LeaveFileController;
use App\Http\Controllers\Leave\LeaveSettingsController;
use App\Http\Controllers\Leave\MyLeaveController;
use App\Http\Controllers\Leave\StaffLeaveController;
use App\Http\Controllers\Leave\StaffLeaveFileController;
use App\Http\Controllers\School\AcademicYearController;
use App\Http\Controllers\School\ApprovalsController;
use App\Http\Controllers\School\CampusController;
use App\Http\Controllers\School\CampusSelectionController;
use App\Http\Controllers\School\ClassesController;
use App\Http\Controllers\School\ClassSubjectsController;
use App\Http\Controllers\School\ClassTeacherController;
use App\Http\Controllers\School\SchoolClassController;
use App\Http\Controllers\School\SectionController;
use App\Http\Controllers\School\SessionSelectionController;
use App\Http\Controllers\School\StructureController;
use App\Http\Controllers\School\SubjectCatalogController;
use App\Http\Controllers\School\SubjectController;
use App\Http\Controllers\School\SubjectsScreenController;
use App\Http\Controllers\School\SubjectTeacherController;
use App\Http\Controllers\School\SyllabusController;
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

Route::middleware(['auth'])->get('classes', [ClassesController::class, 'index'])->name('school.classes.index');

Route::middleware(['auth'])->prefix('subjects')->name('school.subjects.')->group(function () {
    Route::get('/', [SubjectsScreenController::class, 'index'])->name('index');
    Route::post('/', [SubjectCatalogController::class, 'store'])->name('store');
    Route::patch('{subject}', [SubjectCatalogController::class, 'update'])->name('update');
    Route::delete('{subject}', [SubjectCatalogController::class, 'destroy'])->name('destroy');
    Route::put('classes/{schoolClass}', [ClassSubjectsController::class, 'update'])->name('classes.update');
    Route::put('sections/{section}/teachers/{subject}', [SubjectTeacherController::class, 'update'])->name('teachers.update');
});

Route::middleware(['auth'])->prefix('teaching/subjects')->name('school.syllabus.')->group(function () {
    Route::get('/', [SyllabusController::class, 'index'])->name('index');
    Route::put('{section}/{subject}/class', [SyllabusController::class, 'updateClass'])->name('class');
    Route::put('{section}/{subject}/section', [SyllabusController::class, 'updateSection'])->name('section');
    Route::put('{section}/{subject}/students', [SyllabusController::class, 'updateStudents'])->name('students');
});

Route::middleware(['auth'])->get('approvals', [ApprovalsController::class, 'index'])->name('approvals.index');

Route::middleware(['auth'])->prefix('school/structure')->name('school.structure.')->group(function () {
    Route::get('/', [StructureController::class, 'index'])->name('index');

    Route::post('academic-years', [AcademicYearController::class, 'store'])->name('academic-years.store');
    Route::patch('academic-years/{academicYear}/current', [AcademicYearController::class, 'makeCurrent'])->name('academic-years.current');

    Route::post('campuses', [CampusController::class, 'store'])->name('campuses.store');
    Route::patch('campuses/{campus}', [CampusController::class, 'update'])->name('campuses.update');

    Route::post('classes', [SchoolClassController::class, 'store'])->name('classes.store');
    Route::post('classes/bulk', [SchoolClassController::class, 'storeBulk'])->name('classes.bulk');
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

Route::middleware(['auth', 'module:fees'])->prefix('fees')->name('fees.')->group(function () {
    Route::get('dues', [DuesController::class, 'index'])->name('dues.index');
    Route::get('dues/export', [DuesController::class, 'export'])->name('dues.export');
    Route::post('dues/remind', [DuesController::class, 'remind'])->name('dues.remind');

    Route::get('payments', [PaymentController::class, 'index'])->name('payments.index');
    Route::get('payments/export', [PaymentController::class, 'export'])->name('payments.export');
    Route::post('payments/{payment}/check', [PaymentController::class, 'check'])->name('payments.check');
    Route::post('payments/{payment}/recheck', [PaymentController::class, 'recheck'])->name('payments.recheck');
    Route::post('payments/{payment}/cheque', [PaymentController::class, 'cheque'])->name('payments.cheque');
    Route::post('payments/{payment}/link', [PaymentController::class, 'link'])->name('payments.link');
    Route::get('receipts/{payment}', [ReceiptController::class, 'show'])->name('receipts.show');

    Route::get('refunds', [RefundController::class, 'index'])->name('refunds.index');
    Route::post('refunds', [RefundController::class, 'store'])->name('refunds.store');
    Route::post('refunds/{refund}/approve', [RefundController::class, 'approve'])->name('refunds.approve');
    Route::post('refunds/{refund}/reject', [RefundController::class, 'reject'])->name('refunds.reject');

    Route::get('collect', [CollectController::class, 'index'])->name('collect.index');
    Route::post('collect', [CollectController::class, 'store'])->name('collect.store');

    Route::get('structure', [FeeStructureController::class, 'index'])->name('structure.index');
    Route::post('structure/schedule', [FeeStructureController::class, 'schedule'])->name('structure.schedule');
    Route::post('structure/groups', [FeeStructureController::class, 'storeGroup'])->name('structure.groups.store');
    Route::put('structure/groups/{group}', [FeeStructureController::class, 'updateHeads'])->name('structure.groups.update');
    Route::patch('structure/groups/{group}', [FeeStructureController::class, 'rename'])->name('structure.groups.rename');
    Route::delete('structure/groups/{group}', [FeeStructureController::class, 'destroyGroup'])->name('structure.groups.destroy');
    Route::put('structure/classes/{schoolClass}', [FeeStructureController::class, 'assignClass'])->name('structure.classes.assign');
    Route::post('structure/concessions', [FeeStructureController::class, 'storeConcession'])->name('structure.concessions.store');
});

Route::middleware(['auth', 'module:exams'])->prefix('exams')->name('exams.')->group(function () {
    Route::get('grading', [GradeScaleController::class, 'show'])->name('grading');
    Route::put('grading', [GradeScaleController::class, 'update'])->name('grading.update');
    Route::put('grading/report-card-approval', [GradeScaleController::class, 'approval'])->name('grading.approval');
    Route::get('/', [ExamController::class, 'index'])->name('index');
    Route::post('/', [ExamController::class, 'store'])->name('store');
    Route::put('{exam}', [ExamController::class, 'update'])->name('update');
    Route::delete('{exam}', [ExamController::class, 'destroy'])->name('destroy');
    Route::put('{exam}/status', [ExamStatusController::class, 'update'])->name('status');
});

Route::middleware(['auth', 'module:exams'])->prefix('marks')->name('marks.')->group(function () {
    Route::get('/', [MarksController::class, 'index'])->name('index');
    Route::put('{exam}/{section}/{subject}', [MarksController::class, 'update'])->name('update');
    Route::post('{exam}/{section}/{subject}/reopen', [MarksController::class, 'reopen'])->name('reopen');
});

Route::middleware(['auth', 'module:exams'])->prefix('report-cards')->name('report-cards.')->group(function () {
    Route::get('/', [ReportCardController::class, 'index'])->name('index');
    Route::post('{exam}/generate', [ReportCardController::class, 'generate'])->name('generate');
    Route::post('{exam}/upload', [ReportCardController::class, 'upload'])->name('upload');
    Route::post('{exam}/decide', [ReportCardController::class, 'decide'])->name('decide');
    Route::post('cards/{reportCard}/withdraw', [ReportCardController::class, 'withdraw'])->name('withdraw');
    Route::get('cards/{reportCard}/file', [ReportCardController::class, 'file'])->middleware('signed')->name('file');
});

// Student leave is core, not a sellable module: reachable by `leave.approve` or `leave.approve_long` holders (checked in the Actions).
Route::middleware(['auth'])->prefix('leave')->name('leave.')->group(function () {
    Route::get('/', [LeaveController::class, 'index'])->name('index');
    Route::get('settings', [LeaveSettingsController::class, 'show'])->name('settings');
    Route::put('settings', [LeaveSettingsController::class, 'update'])->name('settings.update');
    Route::put('settings/staff', [LeaveSettingsController::class, 'updateStaffPolicy'])->name('settings.staff');
    Route::post('{leave}/decide', [LeaveController::class, 'decide'])->name('decide');
    Route::get('{leave}/attachment', [LeaveFileController::class, 'attachment'])->middleware('signed')->name('attachment');
});

// Staff leave is core too: anyone with a staff record applies on My leave; `staff.leave_approve` holders decide on Staff and leave (checked in the Actions).
Route::middleware(['auth'])->prefix('my-leave')->name('my-leave.')->group(function () {
    Route::get('/', [MyLeaveController::class, 'index'])->name('index');
    Route::post('/', [MyLeaveController::class, 'store'])->name('store');
    Route::post('{leave}/cancel', [MyLeaveController::class, 'cancel'])->name('cancel');
});

Route::middleware(['auth'])->prefix('staff-leave')->name('staff-leave.')->group(function () {
    Route::get('/', [StaffLeaveController::class, 'index'])->name('index');
    Route::post('{leave}/decide', [StaffLeaveController::class, 'decide'])->name('decide');
    Route::get('{leave}/attachment', [StaffLeaveFileController::class, 'attachment'])->middleware('signed')->name('attachment');
});

Route::middleware(['auth', 'module:homework'])->prefix('homework')->name('homework.')->group(function () {
    Route::get('/', [HomeworkController::class, 'index'])->name('index');
    Route::post('/', [HomeworkController::class, 'store'])->name('store');
    Route::put('{assignment}', [HomeworkController::class, 'update'])->name('update');
    Route::delete('{assignment}', [HomeworkController::class, 'destroy'])->name('destroy');
    Route::post('{assignment}/checked', [HomeworkController::class, 'checked'])->name('checked');
    Route::put('{assignment}/feedback', [HomeworkController::class, 'feedback'])->name('feedback');
    Route::get('files/{file}', [HomeworkFileController::class, 'attachment'])->middleware('signed')->name('files.show');
    Route::get('submission-files/{file}', [HomeworkFileController::class, 'submission'])->middleware('signed')->name('submission-files.show');
});
