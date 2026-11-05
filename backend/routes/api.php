<?php

use App\Http\Controllers\Api\V1\AcademicYearController;
use App\Http\Controllers\Api\V1\AttendanceController;
use App\Http\Controllers\Api\V1\Auth\AuthController;
use App\Http\Controllers\Api\V1\BankAccountController;
use App\Http\Controllers\Api\V1\CertificateController;
use App\Http\Controllers\Api\V1\CertificateTemplateController;
use App\Http\Controllers\Api\V1\CommunicationController;
use App\Http\Controllers\Api\V1\ExamController;
use App\Http\Controllers\Api\V1\ExamMarkController;
use App\Http\Controllers\Api\V1\FeeStructureController;
use App\Http\Controllers\Api\V1\GuardianController;
use App\Http\Controllers\Api\V1\MessageTemplateController;
use App\Http\Controllers\Api\V1\IdCardController;
use App\Http\Controllers\Api\V1\IdCardTemplateController;
use App\Http\Controllers\Api\V1\InvoiceController;
use App\Http\Controllers\Api\V1\LeaveController;
use App\Http\Controllers\Api\V1\LocaleController;
use App\Http\Controllers\Api\V1\PayslipController;
use App\Http\Controllers\Api\V1\ResultsController;
use App\Http\Controllers\Api\V1\TimetableController;
use App\Http\Controllers\Api\V1\PaymentController;
use App\Http\Controllers\Api\V1\PermissionController;
use App\Http\Controllers\Api\V1\RoleController;
use App\Http\Controllers\Api\V1\SchoolClassController;
use App\Http\Controllers\Api\V1\StreamController;
use App\Http\Controllers\Api\V1\StudentController;
use App\Http\Controllers\Api\V1\SubjectController;
use App\Http\Controllers\Api\V1\StudentTransportController;
use App\Http\Controllers\Api\V1\SuperAdmin\SchoolController;
use App\Http\Controllers\Api\V1\TransportRouteController;
use App\Http\Controllers\Api\V1\UserController;
use App\Http\Controllers\Api\V1\VehicleController;
use App\Http\Controllers\Api\V1\VehicleLocationController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function () {

    Route::post('/auth/login', [AuthController::class, 'login'])
        ->middleware('throttle:10,1');

    // AzamPay calls this directly — no Sanctum session, no tenant context.
    // Protected by its own shared-secret token (see the controller) instead.
    Route::post('/payments/azampay/callback', [\App\Http\Controllers\Api\V1\PaymentGatewayCallbackController::class, 'azampay']);

    // AzamPay's Bill Pay product calls these three directly — no Sanctum
    // session, no tenant context (the controller authenticates via its
    // own JWT+Hash check instead, same reasoning as the callback above).
    Route::post('/billpay/name-lookup', [\App\Http\Controllers\Api\V1\BillPayController::class, 'nameLookup']);
    Route::post('/billpay/payment', [\App\Http\Controllers\Api\V1\BillPayController::class, 'payment']);
    Route::post('/billpay/status-check', [\App\Http\Controllers\Api\V1\BillPayController::class, 'statusCheck']);

    Route::middleware(['auth:sanctum', 'tenant', \App\Http\Middleware\SetLocale::class])->group(function () {
        Route::get('/auth/me', [AuthController::class, 'me']);
        Route::post('/auth/logout', [AuthController::class, 'logout']);
        Route::put('/auth/locale', [LocaleController::class, 'update']);
        Route::put('/auth/password', [AuthController::class, 'password']);

        // School-scoped (tenant) endpoints.
        Route::apiResource('users', UserController::class)->only(['index', 'store']);
        Route::apiResource('roles', RoleController::class);
        Route::get('/permissions', [PermissionController::class, 'index']);

        Route::apiResource('academic-years', AcademicYearController::class)->only(['index', 'store', 'update']);
        Route::apiResource('classes', SchoolClassController::class)->only(['index', 'store', 'show', 'update', 'destroy']);
        Route::apiResource('streams', StreamController::class)->only(['index', 'store', 'update', 'destroy']);
        Route::apiResource('guardians', GuardianController::class)->only(['index', 'store', 'update', 'destroy']);
        Route::apiResource('students', StudentController::class)->only(['index', 'store', 'show', 'update']);
        Route::get('/attendance/classes', [AttendanceController::class, 'classes']);
        Route::get('/attendance', [AttendanceController::class, 'index']);
        Route::post('/attendance', [AttendanceController::class, 'store']);
        Route::post('/attendance/unlock', [AttendanceController::class, 'unlock']);

        Route::get('/staff-attendance', [\App\Http\Controllers\Api\V1\StaffAttendanceController::class, 'index']);
        Route::post('/staff-attendance', [\App\Http\Controllers\Api\V1\StaffAttendanceController::class, 'store']);
        Route::post('/staff-attendance/unlock', [\App\Http\Controllers\Api\V1\StaffAttendanceController::class, 'unlock']);

        Route::post('/attendance/scan', [\App\Http\Controllers\Api\V1\AttendanceScanController::class, 'store']);

        Route::apiResource('subjects', SubjectController::class)->only(['index', 'store', 'update', 'destroy']);
        Route::apiResource('exams', ExamController::class)->only(['index', 'store', 'show']);
        Route::post('/exams/{exam}/subjects', [ExamController::class, 'addSubject']);
        Route::patch('/exams/{exam}/publish', [ExamController::class, 'publish']);
        Route::get('/exams/{exam}/subjects/{subject}/marks', [ExamMarkController::class, 'index']);
        Route::post('/exams/{exam}/subjects/{subject}/marks', [ExamMarkController::class, 'store']);

        // Learning Hub: lessons, homework, and auto-graded online exams.
        Route::apiResource('lessons', \App\Http\Controllers\Api\V1\LessonController::class)->only(['index', 'store', 'destroy']);
        Route::apiResource('homework', \App\Http\Controllers\Api\V1\HomeworkController::class)->only(['index', 'store', 'destroy']);
        Route::apiResource('online-exams', \App\Http\Controllers\Api\V1\OnlineExamController::class)
            ->parameters(['online-exams' => 'exam'])
            ->only(['index', 'store', 'show', 'destroy']);
        Route::patch('/online-exams/{exam}/publish', [\App\Http\Controllers\Api\V1\OnlineExamController::class, 'publish']);

        Route::apiResource('bank-accounts', BankAccountController::class)->only(['index', 'store']);
        Route::apiResource('fee-structures', FeeStructureController::class)->only(['index', 'store', 'show', 'update', 'destroy']);
        Route::post('/fee-structures/{feeStructure}/invoices', [InvoiceController::class, 'generate']);
        Route::apiResource('invoices', InvoiceController::class)->only(['index', 'show']);
        Route::post('/invoices/{invoice}/payments', [PaymentController::class, 'store']);
        Route::post('/invoices/{invoice}/send-sms', [InvoiceController::class, 'sendSms']);

        Route::apiResource('certificate-templates', CertificateTemplateController::class)->only(['index', 'store']);
        Route::put('/certificate-templates/{certificateTemplate}', [CertificateTemplateController::class, 'update']);
        Route::post('/certificate-templates/{certificateTemplate}/activate', [CertificateTemplateController::class, 'activate']);
        Route::apiResource('certificates', CertificateController::class)->only(['index', 'store', 'show']);
        Route::patch('/certificates/{certificate}/revoke', [CertificateController::class, 'revoke']);

        Route::apiResource('id-card-templates', IdCardTemplateController::class)->only(['index', 'store']);
        Route::put('/id-card-templates/{idCardTemplate}', [IdCardTemplateController::class, 'update']);
        Route::post('/id-card-templates/{idCardTemplate}/activate', [IdCardTemplateController::class, 'activate']);
        Route::apiResource('id-cards', IdCardController::class)->only(['index', 'store', 'show']);
        Route::patch('/id-cards/{idCard}/revoke', [IdCardController::class, 'revoke']);

        Route::apiResource('vehicles', VehicleController::class)->only(['index', 'store', 'show', 'update', 'destroy']);
        Route::post('/vehicles/{vehicle}/location', [VehicleLocationController::class, 'store']);
        Route::apiResource('transport-routes', TransportRouteController::class)->only(['index', 'store', 'show', 'update', 'destroy']);
        Route::patch('/students/{student}/transport', [StudentTransportController::class, 'update']);

        // Parents: their own children, found through the guardian record linked to their login.
        Route::get('/my/children', [\App\Http\Controllers\Api\V1\ParentController::class, 'children']);
        Route::get('/my/children/{student}/bus', [\App\Http\Controllers\Api\V1\ParentController::class, 'bus']);
        Route::get('/my/children/{student}/fees', [\App\Http\Controllers\Api\V1\ParentController::class, 'fees']);
        Route::post('/my/children/{student}/invoices/{invoice}/pay', [\App\Http\Controllers\Api\V1\ParentController::class, 'payInvoice']);
        Route::get('/my/children/{student}/payments/{transaction}', [\App\Http\Controllers\Api\V1\ParentController::class, 'paymentStatus']);
        Route::get('/my/children/{student}/attendance', [\App\Http\Controllers\Api\V1\ParentController::class, 'attendance']);
        Route::get('/my/children/{student}/results', [\App\Http\Controllers\Api\V1\ParentController::class, 'results']);
        Route::get('/my/children/{student}/timetable', [\App\Http\Controllers\Api\V1\ParentController::class, 'timetable']);
        Route::get('/my/children/{student}/lessons', [\App\Http\Controllers\Api\V1\LearningController::class, 'lessons']);
        Route::get('/my/children/{student}/homework', [\App\Http\Controllers\Api\V1\LearningController::class, 'homework']);
        Route::get('/my/children/{student}/online-exams', [\App\Http\Controllers\Api\V1\LearningController::class, 'onlineExams']);
        Route::post('/my/children/{student}/online-exams/{exam}/start', [\App\Http\Controllers\Api\V1\LearningController::class, 'startOnlineExam']);
        Route::post('/my/children/{student}/online-exams/{exam}/submit', [\App\Http\Controllers\Api\V1\LearningController::class, 'submitOnlineExam']);

        // Give a guardian a login (Parent role) / reset their password.
        Route::post('/guardians/{guardian}/account', [\App\Http\Controllers\Api\V1\GuardianAccountController::class, 'store']);
        Route::post('/students/{student}/account', [\App\Http\Controllers\Api\V1\StudentAccountController::class, 'store']);
        Route::post('/students/{student}/account/reset', [\App\Http\Controllers\Api\V1\StudentAccountController::class, 'reset']);
        Route::post('/guardians/{guardian}/account/reset', [\App\Http\Controllers\Api\V1\GuardianAccountController::class, 'reset']);

        // Terms.
        Route::get('/terms', [\App\Http\Controllers\Api\V1\TermController::class, 'index']);
        Route::post('/terms', [\App\Http\Controllers\Api\V1\TermController::class, 'store']);
        Route::put('/terms/{term}', [\App\Http\Controllers\Api\V1\TermController::class, 'update']);
        Route::delete('/terms/{term}', [\App\Http\Controllers\Api\V1\TermController::class, 'destroy']);

        // Staff (HR).
        Route::get('/staff', [\App\Http\Controllers\Api\V1\StaffController::class, 'index']);
        Route::post('/staff', [\App\Http\Controllers\Api\V1\StaffController::class, 'store']);
        Route::put('/staff/{user:uuid}', [\App\Http\Controllers\Api\V1\StaffController::class, 'update']);
        Route::patch('/staff/{user:uuid}/status', [\App\Http\Controllers\Api\V1\StaffController::class, 'status']);

        // Inventory.
        Route::get('/inventory/items', [\App\Http\Controllers\Api\V1\InventoryController::class, 'index']);
        Route::post('/inventory/items', [\App\Http\Controllers\Api\V1\InventoryController::class, 'store']);
        Route::put('/inventory/items/{item}', [\App\Http\Controllers\Api\V1\InventoryController::class, 'update']);
        Route::delete('/inventory/items/{item}', [\App\Http\Controllers\Api\V1\InventoryController::class, 'destroy']);
        Route::get('/inventory/items/{item}/movements', [\App\Http\Controllers\Api\V1\InventoryController::class, 'movements']);
        Route::post('/inventory/movements', [\App\Http\Controllers\Api\V1\InventoryController::class, 'storeMovement']);
        Route::get('/inventory/categories', [\App\Http\Controllers\Api\V1\InventoryController::class, 'categories']);
        Route::post('/inventory/categories', [\App\Http\Controllers\Api\V1\InventoryController::class, 'storeCategory']);

        // Library.
        Route::get('/library/books', [\App\Http\Controllers\Api\V1\LibraryController::class, 'index']);
        Route::post('/library/books', [\App\Http\Controllers\Api\V1\LibraryController::class, 'store']);
        Route::put('/library/books/{book}', [\App\Http\Controllers\Api\V1\LibraryController::class, 'update']);
        Route::delete('/library/books/{book}', [\App\Http\Controllers\Api\V1\LibraryController::class, 'destroy']);
        Route::post('/library/books/{book}/borrow', [\App\Http\Controllers\Api\V1\LibraryController::class, 'borrow']);
        Route::get('/library/loans', [\App\Http\Controllers\Api\V1\LibraryController::class, 'loans']);
        Route::patch('/library/loans/{loan}/return', [\App\Http\Controllers\Api\V1\LibraryController::class, 'returnLoan']);
        Route::patch('/library/loans/{loan}/lost', [\App\Http\Controllers\Api\V1\LibraryController::class, 'loseLoan']);

        // Hostel.
        Route::get('/hostel/hostels', [\App\Http\Controllers\Api\V1\HostelController::class, 'index']);
        Route::post('/hostel/hostels', [\App\Http\Controllers\Api\V1\HostelController::class, 'store']);
        Route::put('/hostel/hostels/{hostel}', [\App\Http\Controllers\Api\V1\HostelController::class, 'update']);
        Route::delete('/hostel/hostels/{hostel}', [\App\Http\Controllers\Api\V1\HostelController::class, 'destroy']);
        Route::get('/hostel/rooms', [\App\Http\Controllers\Api\V1\HostelController::class, 'rooms']);
        Route::post('/hostel/rooms', [\App\Http\Controllers\Api\V1\HostelController::class, 'storeRoom']);
        Route::put('/hostel/rooms/{room}', [\App\Http\Controllers\Api\V1\HostelController::class, 'updateRoom']);
        Route::delete('/hostel/rooms/{room}', [\App\Http\Controllers\Api\V1\HostelController::class, 'destroyRoom']);
        Route::post('/hostel/rooms/{room}/allocate', [\App\Http\Controllers\Api\V1\HostelController::class, 'allocate']);
        Route::get('/hostel/allocations', [\App\Http\Controllers\Api\V1\HostelController::class, 'allocations']);
        Route::patch('/hostel/allocations/{allocation}/vacate', [\App\Http\Controllers\Api\V1\HostelController::class, 'vacate']);

        // Bus trips: the driver starts/ends a trip and streams positions; managers watch live.
        Route::get('/transport/trips/active', [\App\Http\Controllers\Api\V1\TripController::class, 'active']);
        Route::get('/transport/trips/current', [\App\Http\Controllers\Api\V1\TripController::class, 'current']);
        Route::get('/transport/trips', [\App\Http\Controllers\Api\V1\TripController::class, 'index']);
        Route::post('/transport/trips', [\App\Http\Controllers\Api\V1\TripController::class, 'store']);
        Route::get('/transport/trips/{trip}', [\App\Http\Controllers\Api\V1\TripController::class, 'show']);
        Route::post('/transport/trips/{trip}/location', [\App\Http\Controllers\Api\V1\TripController::class, 'location']);
        Route::post('/transport/trips/{trip}/stops/{stop}/arrive', [\App\Http\Controllers\Api\V1\TripController::class, 'arrive']);
        Route::post('/transport/trips/{trip}/end', [\App\Http\Controllers\Api\V1\TripController::class, 'end']);

        Route::get('/message-templates', [MessageTemplateController::class, 'index']);
        Route::post('/message-templates', [MessageTemplateController::class, 'store']);
        Route::put('/message-templates/{messageTemplate}', [MessageTemplateController::class, 'update']);
        Route::delete('/message-templates/{messageTemplate}', [MessageTemplateController::class, 'destroy']);
        Route::post('/message-templates/{messageTemplate}/reset', [MessageTemplateController::class, 'reset']);
        Route::get('/sms-logs', [CommunicationController::class, 'index']);
        Route::post('/sms-logs', [CommunicationController::class, 'store']);

        // Everyday staff/self-service endpoints for the mobile app. The
        // setup-heavy modules (HR admin, inventory, hostel...) stay portal-only.
        Route::get('/timetable/mine', [TimetableController::class, 'mine']);
        Route::get('/timetable/classes/{schoolClass}', [TimetableController::class, 'schoolClass']);

        Route::get('/results/terms', [ResultsController::class, 'terms']);
        Route::get('/results', [ResultsController::class, 'index']);

        Route::get('/my/payslips', [PayslipController::class, 'index']);
        Route::get('/my/payslips/{payslip}', [PayslipController::class, 'show']);

        Route::get('/leave', [LeaveController::class, 'index']);
        Route::post('/leave', [LeaveController::class, 'store']);
        Route::patch('/leave/{leaveRequest}/approve', [LeaveController::class, 'approve']);
        Route::patch('/leave/{leaveRequest}/reject', [LeaveController::class, 'reject']);

        // Platform (Super Admin) endpoints — these users have school_id
        // null, so IdentifyTenant leaves no tenant bound and School::query()
        // legitimately sees every tenant. role:Super Admin is the primary
        // gate; individual actions are further checked against granular
        // permissions inside the controller.
        Route::middleware('role:Super Admin')->prefix('super-admin')->group(function () {
            Route::apiResource('schools', SchoolController::class)->except(['destroy']);
            Route::patch('schools/{school}/suspend', [SchoolController::class, 'suspend']);
            Route::patch('schools/{school}/activate', [SchoolController::class, 'activate']);
            Route::get('dashboard', [\App\Http\Controllers\Api\V1\SuperAdmin\DashboardController::class, 'index']);
        });
    });
});
