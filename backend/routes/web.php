<?php

use App\Http\Controllers\CertificatePdfController;
use App\Http\Controllers\IdCardPdfController;
use App\Http\Controllers\PlaceController;
use App\Http\Controllers\Public\VerificationController;
use App\Http\Controllers\Web\AcademicSetupController;
use App\Http\Controllers\Web\AttendanceController;
use App\Http\Controllers\Web\Auth\PortalAuthController;
use App\Http\Controllers\Web\BankAccountController;
use App\Http\Controllers\Web\CertificateController;
use App\Http\Controllers\Web\CommunicationController;
use App\Http\Controllers\Web\DashboardController;
use App\Http\Controllers\Web\ExamController;
use App\Http\Controllers\Web\ExamMarkController;
use App\Http\Controllers\Web\FeeStructureController;
use App\Http\Controllers\Web\HostelController;
use App\Http\Controllers\Web\IdCardController;
use App\Http\Controllers\Web\InventoryController;
use App\Http\Controllers\Web\LeaveController;
use App\Http\Controllers\Web\LibraryController;
use App\Http\Controllers\Web\InvoiceController;
use App\Http\Controllers\Web\LocaleController;
use App\Http\Controllers\Web\ReportController;
use App\Http\Controllers\Web\SchoolSettingsController;
use App\Http\Controllers\Web\ResultsController;
use App\Http\Controllers\Web\TermController;
use App\Http\Controllers\Web\TimetableController;
use App\Http\Controllers\Web\RoleController;
use App\Http\Controllers\Web\StudentController;
use App\Http\Controllers\Web\PayrollController;
use App\Http\Controllers\Web\ProcurementController;
use App\Http\Controllers\Web\StaffController;
use App\Http\Controllers\Web\SubjectController;
use App\Http\Controllers\Web\SuperAdmin\DashboardController as SuperAdminDashboardController;
use App\Http\Controllers\Web\SuperAdmin\SchoolController as SuperAdminSchoolController;
use App\Http\Controllers\Web\TransportRouteController;
use App\Http\Controllers\Web\VehicleController;
use App\Http\Controllers\Web\VirtualClassroomController;
use Illuminate\Support\Facades\Route;

Route::redirect('/', '/login');

// Public — deliberately outside every auth/tenant middleware. This is
// where a QR code scan lands.
Route::get('/verify/certificates/{token}', [VerificationController::class, 'certificate'])
    ->name('verify.certificate');
Route::get('/verify/id-cards/{token}', [VerificationController::class, 'idCard'])
    ->name('verify.id-card');

Route::middleware('guest')->group(function () {
    Route::get('/login', [PortalAuthController::class, 'create'])->name('login');
    Route::post('/login', [PortalAuthController::class, 'store'])
        ->middleware('throttle:10,1')
        ->name('login.store');
});

// Shared between the portal (session auth) and API/mobile clients (token
// auth via Sanctum) — one route, one controller, instead of duplicating
// PDF generation per entry point.
Route::middleware(['auth:sanctum,web', 'tenant', \App\Http\Middleware\SetLocale::class])->group(function () {
    Route::get('/certificates/{certificate}/pdf', [CertificatePdfController::class, 'show'])
        ->name('certificates.pdf');
    Route::get('/id-cards/{idCard}/pdf', [IdCardPdfController::class, 'show'])
        ->name('id-cards.pdf');
    Route::get('/results/class-pdf', [ResultsController::class, 'classPdf'])->name('school-admin.results.class-pdf');
    Route::get('/results/students/{student}/pdf', [ResultsController::class, 'studentPdf'])->name('school-admin.results.student-pdf');
    Route::get('/payroll/payslips/{payslip}/pdf', [PayrollController::class, 'payslipPdf'])->name('school-admin.payroll.payslips.pdf');
    Route::get('/lessons/{lesson}/video', [\App\Http\Controllers\Web\LessonController::class, 'video'])->name('school-admin.lessons.video');
    Route::get('/homework/{homework}/attachment', [\App\Http\Controllers\Web\HomeworkController::class, 'attachment'])->name('school-admin.homework.attachment');
    Route::get('/api/v1/places/search', [PlaceController::class, 'search'])
        ->name('places.search');
});

Route::middleware(['auth', 'tenant'])->group(function () {
    Route::post('/logout', [PortalAuthController::class, 'destroy'])->name('logout');

    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');

    Route::post('/language/{locale}', [LocaleController::class, 'update'])->name('language.update');
    Route::get('/school-logo', [SchoolSettingsController::class, 'logo'])->name('school.logo');

    Route::prefix('account')->name('school-admin.account.')->group(function () {
        Route::get('/password', [\App\Http\Controllers\Web\AccountController::class, 'editPassword'])->name('password.edit');
        Route::put('/password', [\App\Http\Controllers\Web\AccountController::class, 'updatePassword'])->name('password.update');
    });

    Route::prefix('settings/school')->name('school-admin.settings.school')->group(function () {
        Route::get('/', [SchoolSettingsController::class, 'edit'])->name('');
        Route::put('/', [SchoolSettingsController::class, 'update'])->name('.update');
        Route::post('/logo', [SchoolSettingsController::class, 'uploadLogo'])->name('.logo');
        Route::delete('/logo', [SchoolSettingsController::class, 'removeLogo'])->name('.logo.remove');
    });

    Route::prefix('roles')->name('school-admin.roles.')->group(function () {
        Route::get('/', [RoleController::class, 'index'])->name('index');
        Route::post('/', [RoleController::class, 'store'])->name('store');
        Route::put('/{role}', [RoleController::class, 'update'])->name('update');
        Route::delete('/{role}', [RoleController::class, 'destroy'])->name('destroy');
    });

    // Roles & who holds them, in one place — posts to the roles.* and
    // staff.update routes above, this just combines the views.
    Route::get('/settings', [\App\Http\Controllers\Web\SettingsController::class, 'index'])->name('school-admin.settings.index');

    Route::prefix('academics')->name('school-admin.academics.')->group(function () {
        Route::get('/', [AcademicSetupController::class, 'index'])->name('index');
        Route::post('/years', [AcademicSetupController::class, 'storeYear'])->name('years.store');

        Route::post('/classes', [AcademicSetupController::class, 'storeClasses'])->name('classes.store');
        Route::get('/classes/{schoolClass}', [AcademicSetupController::class, 'showClass'])->name('classes.show');
        Route::put('/classes/{schoolClass}', [AcademicSetupController::class, 'updateClass'])->name('classes.update');
        Route::delete('/classes/{schoolClass}', [AcademicSetupController::class, 'destroyClass'])->name('classes.destroy');
        Route::patch('/classes/{schoolClass}/move/{direction}', [AcademicSetupController::class, 'moveClass'])
            ->whereIn('direction', ['up', 'down'])->name('classes.move');

        Route::post('/streams', [AcademicSetupController::class, 'storeStreams'])->name('streams.store');
        Route::put('/streams/{stream}', [AcademicSetupController::class, 'updateStream'])->name('streams.update');
        Route::delete('/streams/{stream}', [AcademicSetupController::class, 'destroyStream'])->name('streams.destroy');
        Route::patch('/streams/{stream}/move/{direction}', [AcademicSetupController::class, 'moveStream'])
            ->whereIn('direction', ['up', 'down'])->name('streams.move');
    });

    Route::prefix('terms')->name('school-admin.terms.')->group(function () {
        Route::get('/', [TermController::class, 'index'])->name('index');
        Route::post('/', [TermController::class, 'store'])->name('store');
        Route::put('/{term}', [TermController::class, 'update'])->name('update');
        Route::delete('/{term}', [TermController::class, 'destroy'])->name('destroy');
    });

    Route::prefix('results')->name('school-admin.results.')->group(function () {
        Route::get('/', [ResultsController::class, 'index'])->name('index');
        Route::put('/grade-scale', [ResultsController::class, 'updateGradeScale'])->name('grade-scale');
        Route::post('/remarks', [ResultsController::class, 'saveRemarks'])->name('remarks');
    });

    Route::prefix('timetable')->name('school-admin.timetable.')->group(function () {
        Route::get('/', [TimetableController::class, 'index'])->name('index');
        Route::post('/entries', [TimetableController::class, 'saveEntry'])->name('entries.save');
        Route::delete('/entries/{entry}', [TimetableController::class, 'destroyEntry'])->name('entries.destroy');
        Route::put('/days', [TimetableController::class, 'updateDays'])->name('days.update');
        Route::put('/requirements', [TimetableController::class, 'saveRequirements'])->name('requirements.save');
        Route::post('/generate', [TimetableController::class, 'generate'])->name('generate');
        Route::post('/periods', [TimetableController::class, 'storePeriod'])->name('periods.store');
        Route::put('/periods/{period}', [TimetableController::class, 'updatePeriod'])->name('periods.update');
        Route::delete('/periods/{period}', [TimetableController::class, 'destroyPeriod'])->name('periods.destroy');
    });

    Route::prefix('students')->name('school-admin.students.')->group(function () {
        Route::get('/', [StudentController::class, 'index'])->name('index');
        Route::post('/', [StudentController::class, 'store'])->name('store');
        Route::put('/{student}', [StudentController::class, 'update'])->name('update');
        Route::patch('/{student}/archive', [StudentController::class, 'archive'])->name('archive');
        Route::delete('/{student}', [StudentController::class, 'destroy'])->name('destroy');
        Route::get('/{student}/qr', [StudentController::class, 'qr'])->name('qr');
        Route::put('/{student}/guardians/{guardian}', [\App\Http\Controllers\Web\StudentGuardianController::class, 'update'])->name('guardians.update');
    });

    Route::prefix('guardians')->name('school-admin.guardians.')->group(function () {
        Route::get('/', [\App\Http\Controllers\Web\GuardianController::class, 'index'])->name('index');
        Route::put('/{guardian}', [\App\Http\Controllers\Web\GuardianController::class, 'update'])->name('update');
        Route::delete('/{guardian}', [\App\Http\Controllers\Web\GuardianController::class, 'destroy'])->name('destroy');
        Route::post('/{guardian}/account', [\App\Http\Controllers\Web\GuardianAccountController::class, 'store'])->name('account.store');
        Route::post('/{guardian}/account/reset', [\App\Http\Controllers\Web\GuardianAccountController::class, 'reset'])->name('account.reset');
    });

    Route::prefix('attendance')->name('school-admin.attendance.')->group(function () {
        Route::get('/', [AttendanceController::class, 'index'])->name('index');
        Route::post('/', [AttendanceController::class, 'store'])->name('store');
        Route::post('/unlock', [AttendanceController::class, 'unlock'])->name('unlock');
    });

    Route::prefix('subjects')->name('school-admin.subjects.')->group(function () {
        Route::get('/', [SubjectController::class, 'index'])->name('index');
        Route::post('/', [SubjectController::class, 'store'])->name('store');
        Route::put('/{subject}', [SubjectController::class, 'update'])->name('update');
        Route::delete('/{subject}', [SubjectController::class, 'destroy'])->name('destroy');
    });

    Route::prefix('exams')->name('school-admin.exams.')->group(function () {
        Route::get('/', [ExamController::class, 'index'])->name('index');
        Route::post('/', [ExamController::class, 'store'])->name('store');
        Route::get('/{exam}', [ExamController::class, 'show'])->name('show');
        Route::post('/{exam}/subjects', [ExamController::class, 'addSubject'])->name('subjects.store');
        Route::patch('/{exam}/publish', [ExamController::class, 'publish'])->name('publish');
        Route::get('/{exam}/subjects/{subject}/marks', [ExamMarkController::class, 'index'])->name('marks.index');
        Route::post('/{exam}/subjects/{subject}/marks', [ExamMarkController::class, 'store'])->name('marks.store');
    });

    Route::prefix('lessons')->name('school-admin.lessons.')->group(function () {
        Route::get('/', [\App\Http\Controllers\Web\LessonController::class, 'index'])->name('index');
        Route::post('/', [\App\Http\Controllers\Web\LessonController::class, 'store'])->name('store');
        Route::delete('/{lesson}', [\App\Http\Controllers\Web\LessonController::class, 'destroy'])->name('destroy');
    });

    Route::prefix('homework')->name('school-admin.homework.')->group(function () {
        Route::get('/', [\App\Http\Controllers\Web\HomeworkController::class, 'index'])->name('index');
        Route::post('/', [\App\Http\Controllers\Web\HomeworkController::class, 'store'])->name('store');
        Route::delete('/{homework}', [\App\Http\Controllers\Web\HomeworkController::class, 'destroy'])->name('destroy');
    });

    Route::prefix('online-exams')->name('school-admin.online-exams.')->group(function () {
        Route::get('/', [\App\Http\Controllers\Web\OnlineExamController::class, 'index'])->name('index');
        Route::post('/', [\App\Http\Controllers\Web\OnlineExamController::class, 'store'])->name('store');
        Route::get('/{exam}', [\App\Http\Controllers\Web\OnlineExamController::class, 'show'])->name('show');
        Route::patch('/{exam}/publish', [\App\Http\Controllers\Web\OnlineExamController::class, 'publish'])->name('publish');
        Route::delete('/{exam}', [\App\Http\Controllers\Web\OnlineExamController::class, 'destroy'])->name('destroy');
    });

    Route::prefix('bank-accounts')->name('school-admin.bank-accounts.')->group(function () {
        Route::get('/', [BankAccountController::class, 'index'])->name('index');
        Route::post('/', [BankAccountController::class, 'store'])->name('store');
        Route::put('/{bankAccount}', [BankAccountController::class, 'update'])->name('update');
        Route::delete('/{bankAccount}', [BankAccountController::class, 'destroy'])->name('destroy');
    });

    Route::prefix('fee-structures')->name('school-admin.fee-structures.')->group(function () {
        Route::get('/', [FeeStructureController::class, 'index'])->name('index');
        Route::post('/', [FeeStructureController::class, 'store'])->name('store');
        Route::put('/{feeStructure}', [FeeStructureController::class, 'update'])->name('update');
        Route::delete('/{feeStructure}', [FeeStructureController::class, 'destroy'])->name('destroy');
        Route::post('/{feeStructure}/invoices', [InvoiceController::class, 'generateForStructure'])->name('invoices.store');
    });

    Route::prefix('invoices')->name('school-admin.invoices.')->group(function () {
        Route::get('/', [InvoiceController::class, 'index'])->name('index');
        Route::get('/{invoice}', [InvoiceController::class, 'show'])->name('show');
        Route::post('/{invoice}/payments', [InvoiceController::class, 'recordPayment'])->name('payments.store');
        Route::post('/{invoice}/reminder', [InvoiceController::class, 'sendReminder'])->name('reminder.store');
    });

    Route::prefix('certificates')->name('school-admin.certificates.')->group(function () {
        Route::get('/', [CertificateController::class, 'index'])->name('index');
        Route::post('/', [CertificateController::class, 'store'])->name('store');
        Route::patch('/{certificate}/revoke', [CertificateController::class, 'revoke'])->name('revoke');
        Route::post('/templates', [CertificateController::class, 'storeTemplate'])->name('templates.store');
        Route::put('/templates/{certificateTemplate}', [CertificateController::class, 'updateTemplate'])->name('templates.update');
        Route::post('/templates/{certificateTemplate}/activate', [CertificateController::class, 'activateTemplate'])->name('templates.activate');
        Route::delete('/templates/{certificateTemplate}', [CertificateController::class, 'destroyTemplate'])->name('templates.destroy');
    });

    Route::prefix('id-cards')->name('school-admin.id-cards.')->group(function () {
        Route::get('/', [IdCardController::class, 'index'])->name('index');
        Route::post('/', [IdCardController::class, 'store'])->name('store');
        Route::patch('/{idCard}/revoke', [IdCardController::class, 'revoke'])->name('revoke');
        Route::post('/templates', [IdCardController::class, 'storeTemplate'])->name('templates.store');
        Route::put('/templates/{idCardTemplate}', [IdCardController::class, 'updateTemplate'])->name('templates.update');
        Route::post('/templates/{idCardTemplate}/activate', [IdCardController::class, 'activateTemplate'])->name('templates.activate');
        Route::delete('/templates/{idCardTemplate}', [IdCardController::class, 'destroyTemplate'])->name('templates.destroy');
    });

    Route::get('/transport/live', [\App\Http\Controllers\Web\TransportLiveController::class, 'index'])->name('school-admin.transport-live');
    Route::get('/transport/live/data', [\App\Http\Controllers\Web\TransportLiveController::class, 'data'])->name('school-admin.transport-live.data');

    Route::prefix('vehicles')->name('school-admin.vehicles.')->group(function () {
        Route::get('/', [VehicleController::class, 'index'])->name('index');
        Route::post('/', [VehicleController::class, 'store'])->name('store');
        Route::patch('/{vehicle}', [VehicleController::class, 'update'])->name('update');
        Route::delete('/{vehicle}', [VehicleController::class, 'destroy'])->name('destroy');
    });

    Route::prefix('hostel')->name('school-admin.hostel.')->group(function () {
        Route::get('/', [HostelController::class, 'index'])->name('index');
        Route::post('/', [HostelController::class, 'store'])->name('store');
        Route::put('/{hostel}', [HostelController::class, 'update'])->name('update');
        Route::delete('/{hostel}', [HostelController::class, 'destroy'])->name('destroy');
        Route::get('/rooms', [HostelController::class, 'rooms'])->name('rooms');
        Route::post('/rooms', [HostelController::class, 'storeRoom'])->name('rooms.store');
        Route::put('/rooms/{room}', [HostelController::class, 'updateRoom'])->name('rooms.update');
        Route::delete('/rooms/{room}', [HostelController::class, 'destroyRoom'])->name('rooms.destroy');
        Route::post('/rooms/{room}/allocate', [HostelController::class, 'allocate'])->name('rooms.allocate');
        Route::get('/allocations', [HostelController::class, 'allocations'])->name('allocations');
        Route::patch('/allocations/{allocation}/vacate', [HostelController::class, 'vacate'])->name('allocations.vacate');
    });

    Route::prefix('library')->name('school-admin.library.')->group(function () {
        Route::get('/', [LibraryController::class, 'index'])->name('index');
        Route::post('/', [LibraryController::class, 'store'])->name('store');
        Route::put('/{book}', [LibraryController::class, 'update'])->name('update');
        Route::delete('/{book}', [LibraryController::class, 'destroy'])->name('destroy');
        Route::post('/{book}/borrow', [LibraryController::class, 'borrow'])->name('borrow');
        Route::get('/loans', [LibraryController::class, 'loans'])->name('loans');
        Route::patch('/loans/{loan}/return', [LibraryController::class, 'returnLoan'])->name('loans.return');
        Route::patch('/loans/{loan}/lost', [LibraryController::class, 'loseLoan'])->name('loans.lost');
    });

    Route::prefix('inventory')->name('school-admin.inventory.')->group(function () {
        Route::get('/', [InventoryController::class, 'index'])->name('index');
        Route::post('/categories', [InventoryController::class, 'storeCategory'])->name('categories.store');
        Route::put('/categories/{category}', [InventoryController::class, 'updateCategory'])->name('categories.update');
        Route::delete('/categories/{category}', [InventoryController::class, 'destroyCategory'])->name('categories.destroy');
        Route::post('/', [InventoryController::class, 'store'])->name('store');
        Route::put('/{item}', [InventoryController::class, 'update'])->name('update');
        Route::delete('/{item}', [InventoryController::class, 'destroy'])->name('destroy');
        Route::get('/{item}/movements', [InventoryController::class, 'movements'])->name('movements');
        Route::post('/movements', [InventoryController::class, 'storeMovement'])->name('movements.store');
    });

    Route::prefix('virtual-classroom')->name('school-admin.virtual-classroom.')->group(function () {
        Route::get('/', [VirtualClassroomController::class, 'index'])->name('index');
        Route::post('/', [VirtualClassroomController::class, 'store'])->name('store');
        Route::put('/{virtualClass}', [VirtualClassroomController::class, 'update'])->name('update');
        Route::delete('/{virtualClass}', [VirtualClassroomController::class, 'destroy'])->name('destroy');
        Route::patch('/{virtualClass}/cancel', [VirtualClassroomController::class, 'cancel'])->name('cancel');
        Route::post('/{virtualClass}/invites', [VirtualClassroomController::class, 'sendInvites'])->name('invites');
    });

    Route::prefix('procurement')->name('school-admin.procurement.')->group(function () {
        Route::get('/', [ProcurementController::class, 'index'])->name('index');
        Route::post('/', [ProcurementController::class, 'store'])->name('store');
        Route::patch('/{purchaseRequest}/approve', [ProcurementController::class, 'approve'])->name('approve');
        Route::patch('/{purchaseRequest}/reject', [ProcurementController::class, 'reject'])->name('reject');
        Route::patch('/{purchaseRequest}/receive', [ProcurementController::class, 'receive'])->name('receive');
    });

    Route::prefix('staff')->name('school-admin.staff.')->group(function () {
        Route::get('/', [StaffController::class, 'index'])->name('index');
        Route::post('/', [StaffController::class, 'store'])->name('store');
        Route::patch('/{staffProfile}', [StaffController::class, 'updateProfile'])->name('update');
        Route::patch('/{user}/toggle-status', [StaffController::class, 'toggleStatus'])->name('toggle-status');
        Route::get('/{user}/qr', [StaffController::class, 'qr'])->name('qr');
    });

    Route::prefix('staff-attendance')->name('school-admin.staff-attendance.')->group(function () {
        Route::get('/', [\App\Http\Controllers\Web\StaffAttendanceController::class, 'index'])->name('index');
        Route::post('/', [\App\Http\Controllers\Web\StaffAttendanceController::class, 'store'])->name('store');
        Route::post('/unlock', [\App\Http\Controllers\Web\StaffAttendanceController::class, 'unlock'])->name('unlock');
    });

    Route::prefix('attendance-scan')->name('school-admin.attendance-scan.')->group(function () {
        Route::get('/', [\App\Http\Controllers\Web\AttendanceScanController::class, 'index'])->name('index');
        Route::post('/', [\App\Http\Controllers\Web\AttendanceScanController::class, 'store'])->name('store');
    });

    Route::prefix('payroll')->name('school-admin.payroll.')->group(function () {
        Route::post('/components/statutory', [PayrollController::class, 'installStatutory'])->name('components.statutory');
        Route::post('/adjustments', [PayrollController::class, 'storeAdjustment'])->name('adjustments.store');
        Route::delete('/adjustments/{adjustment}', [PayrollController::class, 'destroyAdjustment'])->name('adjustments.destroy');
        Route::post('/runs/{run}/email', [PayrollController::class, 'emailPayslips'])->name('runs.email');
        Route::post('/runs/{run}/sms', [PayrollController::class, 'smsPayslips'])->name('runs.sms');
        Route::get('/', [PayrollController::class, 'index'])->name('index');
        Route::post('/runs', [PayrollController::class, 'storeRun'])->name('runs.store');
        Route::get('/runs/{run}', [PayrollController::class, 'showRun'])->name('runs.show');
        Route::patch('/runs/{run}/regenerate', [PayrollController::class, 'regenerate'])->name('runs.regenerate');
        Route::patch('/runs/{run}/approve', [PayrollController::class, 'approve'])->name('runs.approve');
        Route::delete('/runs/{run}', [PayrollController::class, 'destroyRun'])->name('runs.destroy');
        Route::post('/runs/{run}/disburse-all', [PayrollController::class, 'disburseAll'])->name('runs.disburse-all');
        Route::get('/runs/{run}/export', [PayrollController::class, 'export'])->name('runs.export');
        Route::post('/payslips/{payslip}/disburse', [PayrollController::class, 'disburse'])->name('payslips.disburse');
        Route::post('/components', [PayrollController::class, 'storeComponent'])->name('components.store');
        Route::put('/components/{component}', [PayrollController::class, 'updateComponent'])->name('components.update');
        Route::delete('/components/{component}', [PayrollController::class, 'destroyComponent'])->name('components.destroy');
        Route::put('/staff/{staffProfile}/salary', [PayrollController::class, 'updateSalary'])->name('staff.salary');
    });

    Route::prefix('leave')->name('school-admin.leave.')->group(function () {
        Route::get('/', [LeaveController::class, 'index'])->name('index');
        Route::post('/', [LeaveController::class, 'store'])->name('store');
        Route::patch('/{leaveRequest}/approve', [LeaveController::class, 'approve'])->name('approve');
        Route::patch('/{leaveRequest}/reject', [LeaveController::class, 'reject'])->name('reject');
        Route::post('/types', [LeaveController::class, 'storeType'])->name('types.store');
        Route::put('/types/{leaveType}', [LeaveController::class, 'updateType'])->name('types.update');
        Route::delete('/types/{leaveType}', [LeaveController::class, 'destroyType'])->name('types.destroy');
    });

    Route::prefix('reports')->name('school-admin.reports.')->group(function () {
        Route::get('/', [ReportController::class, 'index'])->name('index');
        Route::get('/enrollment', [ReportController::class, 'enrollment'])->name('enrollment');
        Route::get('/financial', [ReportController::class, 'financial'])->name('financial');
        Route::get('/attendance', [ReportController::class, 'attendance'])->name('attendance');
        Route::get('/academic', [ReportController::class, 'academic'])->name('academic');
    });

    Route::prefix('communications')->name('school-admin.communications.')->group(function () {
        Route::get('/', [CommunicationController::class, 'index'])->name('index');
        Route::post('/', [CommunicationController::class, 'store'])->name('store');
        Route::post('/templates', [CommunicationController::class, 'storeTemplate'])->name('templates.store');
        Route::put('/templates/{messageTemplate}', [CommunicationController::class, 'updateTemplate'])->name('templates.update');
        Route::post('/templates/{messageTemplate}/reset', [CommunicationController::class, 'resetTemplate'])->name('templates.reset');
        Route::delete('/templates/{messageTemplate}', [CommunicationController::class, 'destroyTemplate'])->name('templates.destroy');
    });

    Route::prefix('transport-routes')->name('school-admin.transport-routes.')->group(function () {
        Route::get('/', [TransportRouteController::class, 'index'])->name('index');
        Route::post('/', [TransportRouteController::class, 'store'])->name('store');
        Route::patch('/{transportRoute}', [TransportRouteController::class, 'update'])->name('update');
        Route::delete('/{transportRoute}', [TransportRouteController::class, 'destroy'])->name('destroy');
    });

    Route::middleware('role:Super Admin')->prefix('super-admin')->name('super-admin.')->group(function () {
        Route::get('/dashboard', [SuperAdminDashboardController::class, 'index'])->name('dashboard');
        Route::post('/schools', [SuperAdminSchoolController::class, 'store'])->name('schools.store');
        Route::patch('/schools/{school}/suspend', [SuperAdminSchoolController::class, 'suspend'])->name('schools.suspend');
        Route::patch('/schools/{school}/activate', [SuperAdminSchoolController::class, 'activate'])->name('schools.activate');
    });
});
