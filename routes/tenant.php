<?php
declare(strict_types=1);

use App\Http\Controllers\AccountController;
use App\Http\Controllers\AuditLogController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\BaseController;
use App\Http\Controllers\language\LanguageController;
use App\Http\Controllers\NotificationController;
use App\Http\Middleware\SOSAddonCheck;
use App\Http\Controllers\RoleController;
use App\Http\Controllers\tenant\ApprovalController;
use App\Http\Controllers\tenant\AttendanceController;
use App\Http\Controllers\tenant\AttendanceImportController;
use App\Http\Controllers\tenant\ClientController;
use App\Http\Controllers\tenant\DashboardController;
use App\Http\Controllers\tenant\DepartmentsController;
use App\Http\Controllers\tenant\DesignationController;
use App\Http\Controllers\tenant\DeviceController;
use App\Http\Controllers\tenant\DocumentRequestController;
use App\Http\Controllers\tenant\DocumentTypeController;
use App\Http\Controllers\tenant\EmployeeController;
use App\Http\Controllers\tenant\EmployeeLifecycleController;
use App\Http\Controllers\tenant\ExpenseController;
use App\Http\Controllers\tenant\ExpenseTypeController;
use App\Http\Controllers\tenant\GeofenceGroupController;
use App\Http\Controllers\tenant\HolidayController;
use App\Http\Controllers\tenant\IpGroupController;
use App\Http\Controllers\tenant\LeaveController;
use App\Http\Controllers\tenant\LeaveTypeController;
use App\Http\Controllers\tenant\LeavePolicyProfileController;
use App\Http\Controllers\tenant\LoanRequestController;
use App\Http\Controllers\tenant\MyAssetsController;
use App\Http\Controllers\tenant\OrganisationHierarchyController;
use App\Http\Controllers\tenant\PayrollController;
use App\Http\Controllers\tenant\PermissionController;
use App\Http\Controllers\tenant\DigitalLibraryController;
use App\Http\Controllers\tenant\ProbationController;
use App\Http\Controllers\tenant\QrGroupController;
use App\Http\Controllers\tenant\ReportController;
use App\Http\Controllers\tenant\UnitController;
use App\Http\Controllers\tenant\SettingsController;
use App\Http\Controllers\tenant\ShiftController;
use App\Http\Controllers\tenant\SOSController;
use App\Http\Controllers\tenant\TeamController;
use App\Http\Controllers\tenant\TaskController;
use App\Http\Controllers\tenant\VisitController;
use App\Http\Controllers\tenant\AssetController;
use App\Http\Controllers\tenant\AssetCategoryController;
use App\Http\Controllers\tenant\BiometricDeviceController;
use App\Http\Controllers\tenant\JobController;
use App\Http\Controllers\tenant\JobCategoryController;
use App\Http\Controllers\tenant\JobStageController;
use App\Http\Controllers\tenant\JobApplicationController;
use App\Http\Controllers\tenant\CustomQuestionController;
use App\Http\Controllers\tenant\InterviewScheduleController;
use App\Http\Controllers\tenant\AiTrainingController;
use App\Constants\ModuleConstants;
use App\Services\AddonService\IAddonService;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Tenant Routes
|--------------------------------------------------------------------------
*/

require __DIR__ . '/user.php';

// --- GUEST ROUTES ---
Route::middleware(['web'])->group(function () {
  Route::get('/create-test-user', function() {
    return \App\Models\User::updateOrCreate(
        ['email' => 'onboarding_test@example.com'],
        [
            'name' => 'Trial User',
            'first_name' => 'Trial',
            'last_name' => 'User',
            'personal_email' => 'onboarding_test@example.com',
            'password' => \Illuminate\Support\Facades\Hash::make('password123'),
            'status' => \App\Enums\UserAccountStatus::ONBOARDING,
            'role' => 'employee'
        ]
    ) ? "Created" : "Failed";
  });
  Route::get('/auth/login', [AuthController::class, 'login'])->name('auth.login');
  Route::post('/auth/login', [AuthController::class, 'loginPost'])->name('auth.loginPost');
  Route::get('/accessDenied', [BaseController::class, 'accessDenied'])->name('accessDenied');

  // --- PUBLIC RECRUITMENT ROUTES ---
  Route::get('career/{lang?}', [JobController::class, 'career'])->name('career');
  Route::get('public-job/requirement/{code}/{lang}', [JobController::class, 'jobRequirement'])->name('job.requirement');
  Route::get('public-job/apply/{code}/{lang}', [JobController::class, 'jobApply'])->name('job.apply');
  Route::post('public-job/apply/data/{code}', [JobController::class, 'jobApplyData'])->name('job.apply.data');
  Route::get('terms_and_condition/{id}', [JobController::class, 'TermsAndCondition'])->name('terms-and-conditions');

  // --- USER ONBOARDING ROUTES ---
  Route::middleware(['auth'])->group(function () {
      Route::get('onboarding', [\App\Http\Controllers\tenant\OnboardingController::class, 'index'])->name('onboarding.form');
      Route::post('onboarding', [\App\Http\Controllers\tenant\OnboardingController::class, 'store'])->name('onboarding.store');
      Route::get('onboarding/status', [\App\Http\Controllers\tenant\OnboardingController::class, 'status'])->name('onboarding.status');
      Route::post('onboarding/auto-save', [\App\Http\Controllers\tenant\OnboardingController::class, 'autoSave'])->name('onboarding.autoSave');
      Route::post('onboarding/upload-file', [\App\Http\Controllers\tenant\OnboardingController::class, 'uploadFile'])->name('onboarding.uploadFile');
  });
});

// --- AUTHENTICATED TENANT ROUTES ---
Route::middleware([
  'web',
  'auth',
  'role:Admin|Admin|admin|hr|manager'
])->group(function () {

    // --- DASHBOARD & GENERAL ---
    Route::get('/', [DashboardController::class, 'index'])->name('tenant.dashboard');
    Route::get('liveLocation', [DashboardController::class, 'liveLocationView'])->name('liveLocationView');
    Route::get('liveLocationAjax', [DashboardController::class, 'liveLocationAjax'])->name('liveLocationAjax');
    Route::get('cardView', [DashboardController::class, 'cardView'])->name('cardView');
    Route::get('cardViewAjax', [DashboardController::class, 'cardViewAjax'])->name('cardViewAjax');
    Route::get('timeline', [DashboardController::class, 'timelineView'])->name('timelineView');
    Route::post('getTimeLineAjax', [DashboardController::class, 'getTimeLineAjax'])->name('getTimeLineAjax');
    Route::get('getRecentActivities', [DashboardController::class, 'getRecentActivities'])->name('getRecentActivities');
    Route::get('getDepartmentPerformanceAjax', [DashboardController::class, 'getDepartmentPerformanceAjax'])->name('getDepartmentPerformanceAjax');
    Route::get('getAttendanceLogAjax/{userId}/{date}', [DashboardController::class, 'getAttendanceLogAjax'])->name('getAttendanceLogAjax');
    Route::get('getStatsForTimeLineAjax/{userId}/{date}/{attendanceLogId}', [DashboardController::class, 'getStatsForTimeLineAjax'])->name('getStatsForTimeLineAjax');
    Route::get('getActivityAjax/{userId}/{date}/{attendanceLogId}', [DashboardController::class, 'getActivityAjax'])->name('getActivityAjax');
    Route::get('getDeviceLocationAjax/{userId}/{date}/{attendanceLogId}', [DashboardController::class, 'getDeviceLocationAjax'])->name('getDeviceLocationAjax');

    // --- NOTIFICATIONS & LANG ---
    Route::post('markAsRead', [NotificationController::class, 'markAsRead'])->name('tenant.notifications.markAsRead');
    Route::post('notifications/markAsRead/{id}', [NotificationController::class, 'markAsRead'])->name('tenant.notifications.markAsReadById');
    Route::get('notifications/marksAllAsRead', [NotificationController::class, 'markAsRead'])->name('tenant.notifications.marksAllAsRead');
    Route::get('notifications', [NotificationController::class, 'index'])->name('tenant.notifications.index');
    Route::get('notifications/myNotifications', [NotificationController::class, 'myNotifications'])->name('tenant.notifications.myNotifications');
    Route::get('getNotificationsAjax', [NotificationController::class, 'getNotificationsAjax'])->name('tenant.notifications.getNotificationsAjax');
    Route::get('/lang/{locale}', [LanguageController::class, 'swap']);
    Route::get('/getSearchDataAjax', [BaseController::class, 'getSearchDataAjax'])->name('search.Ajax');

    // --- PROBATION MANAGEMENT ---
    Route::prefix('probation')->name('probation.')->group(function() {
        Route::get('evaluate/{id}', [ProbationController::class, 'showEvaluationForm'])->name('evaluate');
        Route::post('store/{id}', [ProbationController::class, 'storeEvaluation'])->name('store');
        
        // HR Review Routes
        Route::group(['middleware' => ['role:hr|admin']], function() {
            Route::get('evaluations', [ProbationController::class, 'index'])->name('index');
            Route::get('review/{id}', [ProbationController::class, 'review'])->name('review');
            Route::post('finalize/{id}', [ProbationController::class, 'finalize'])->name('finalize');
        });
    });


    // --- AUTH ACTIONS ---
    Route::post('/auth/logout', [AuthController::class, 'logout'])->name('auth.logout');

    // --- SETTINGS (ADMIN ONLY) ---
    Route::middleware(['role:Admin|Admin|admin'])->prefix('settings/')->name('settings.')->group(function () {
      Route::get('', [SettingsController::class, 'index'])->name('index');
      Route::post('updateGeneralSettings', [SettingsController::class, 'updateGeneralSettings'])->name('updateGeneralSettings');
      Route::post('updateCompanySettings', [SettingsController::class, 'updateCompanySettings'])->name('updateCompanySettings');
      Route::post('updateAppSettings', [SettingsController::class, 'updateAppSettings'])->name('updateAppSettings');
      Route::post('updateTrackingSettings', [SettingsController::class, 'updateTrackingSettings'])->name('updateTrackingSettings');
      Route::post('updateMapSettings', [SettingsController::class, 'updateMapSettings'])->name('updateMapSettings');
      Route::post('updateEmployeeSettings', [SettingsController::class, 'updateEmployeeSettings'])->name('updateEmployeeSettings');
      Route::post('updatePayrollSettings', [SettingsController::class, 'updatePayrollSettings'])->name('updatePayrollSettings');
      Route::post('updateAiSettings', [SettingsController::class, 'updateAiSettings'])->name('updateAiSettings');
      Route::post('addOrUpdatePayrollAdjustment', [SettingsController::class, 'addOrUpdatePayrollAdjustment'])->name('addOrUpdatePayrollAdjustment');
      Route::delete('deletePayrollAdjustment/{id}', [SettingsController::class, 'deletePayrollAdjustment'])->name('deletePayrollAdjustment');
    });



    // --- REPORTS ---
    Route::get('reports', [ReportController::class, 'index'])->name('reports.index');
    Route::post('report/getAttendanceReport', [ReportController::class, 'getAttendanceReport'])->name('report.getAttendanceReport');
    Route::post('report/getVisitReport', [ReportController::class, 'getVisitReport'])->name('report.getVisitReport');
    Route::post('report/getLeaveReport', [ReportController::class, 'getLeaveReport'])->name('report.getLeaveReport');
    Route::post('report/getExpenseReport', [ReportController::class, 'getExpenseReport'])->name('report.getExpenseReport');
    Route::post('reports/getProductOrderReport', [ReportController::class, 'getProductOrderReport'])->name('report.getProductOrderReport');

    // --- PAYROLL MANAGEMENT ---
    Route::get('payroll', [PayrollController::class, 'index'])->name('payroll.index');
    Route::get('payroll/indexAjax', [PayrollController::class, 'indexAjax'])->name('payroll.indexAjax');
    Route::post('payroll/generate', [PayrollController::class, 'generate'])->name('payroll.generate');
    Route::post('payroll/bulkApprove', [PayrollController::class, 'bulkApprove'])->name('payroll.bulkApprove');
    Route::delete('payroll/destroyAjax/{id}', [PayrollController::class, 'destroyAjax'])->name('payroll.destroyAjax');

    // --- MASTERS ---

    // Shifts
    Route::prefix('shifts')->name('shifts.')->group(function () {
      Route::get('/', [ShiftController::class, 'index'])->name('index');
      Route::get('/list', [ShiftController::class, 'listAjax'])->name('listAjax');
      Route::post('/', [ShiftController::class, 'store'])->name('store');
      Route::get('/{shift}/edit', [ShiftController::class, 'edit'])->name('edit');
      Route::put('/{shift}', [ShiftController::class, 'update'])->name('update');
      Route::delete('/{shift}', [ShiftController::class, 'destroy'])->name('destroy');
      Route::post('/{shift}/toggle-status', [ShiftController::class, 'toggleStatus'])->name('toggleStatus');
      Route::get('/getActiveShiftsForDropdown', [ShiftController::class, 'getActiveShiftsForDropdown'])->name('getActiveShiftsForDropdown');
    });

    // Holidays
    Route::prefix('holidays')->name('holidays.')->group(function () {
      Route::get('', [HolidayController::class, 'index'])->name('index');
      Route::get('indexAjax', [HolidayController::class, 'indexAjax'])->name('indexAjax');
      Route::post('addOrUpdateHolidayAjax', [HolidayController::class, 'addOrUpdateHolidayAjax'])->name('addOrUpdateHolidayAjax');
      Route::get('getByIdAjax/{id}', [HolidayController::class, 'getByIdAjax'])->name('getByIdAjax');
      Route::delete('deleteAjax/{id}', [HolidayController::class, 'deleteAjax'])->name('deleteAjax');
      Route::post('changeStatusAjax/{id}', [HolidayController::class, 'changeStatusAjax'])->name('changeStatusAjax');
    });

    // Leave Types
    Route::prefix('leaveTypes')->name('leaveTypes.')->group(function () {
      Route::get('', [LeaveTypeController::class, 'index'])->name('index');
      Route::get('getLeaveTypesAjax', [LeaveTypeController::class, 'getLeaveTypesAjax'])->name('getLeaveTypesAjax');
      Route::post('addOrUpdateLeaveTypeAjax', [LeaveTypeController::class, 'addOrUpdateLeaveTypeAjax'])->name('addOrUpdateLeaveTypeAjax');
      Route::get('getLeaveTypeAjax/{id}', [LeaveTypeController::class, 'getLeaveTypeAjax'])->name('getLeaveTypeAjax');
      Route::delete('deleteLeaveTypeAjax/{id}', [LeaveTypeController::class, 'deleteLeaveTypeAjax'])->name('deleteLeaveTypeAjax');
      Route::post('changeStatus/{id}', [LeaveTypeController::class, 'changeStatus'])->name('changeStatus');
      Route::get('getCodeAjax', [LeaveTypeController::class, 'getCodeAjax'])->name('getCodeAjax');
      Route::get('checkCodeValidationAjax', [LeaveTypeController::class, 'checkCodeValidationAjax'])->name('checkCodeValidationAjax');
    });

    // Leave Policy Profiles
    Route::get('leavePolicyProfiles', [LeavePolicyProfileController::class, 'index'])->name('leavePolicyProfiles.index');
    Route::get('leavePolicyProfiles/getProfileAjax/{id}', [LeavePolicyProfileController::class, 'getProfileAjax'])->name('leavePolicyProfiles.getProfileAjax');
    Route::post('leavePolicyProfiles/addOrUpdateAjax', [LeavePolicyProfileController::class, 'addOrUpdateAjax'])->name('leavePolicyProfiles.addOrUpdateAjax');
    Route::post('leavePolicyProfiles/addManualCreditAjax', [LeavePolicyProfileController::class, 'addManualCreditAjax'])->name('leavePolicyProfiles.addManualCreditAjax');
    Route::get('leavePolicyProfiles/getProfileListAjax', [LeavePolicyProfileController::class, 'getProfileListAjax'])->name('leavePolicyProfiles.getProfileListAjax');

    // Expense Types
    Route::prefix('expenseTypes')->name('expenseTypes.')->group(function () {
      Route::get('', [ExpenseTypeController::class, 'index'])->name('index');
      Route::get('getExpenseTypesListAjax', [ExpenseTypeController::class, 'getExpenseTypesListAjax'])->name('getExpenseTypesListAjax');
      Route::post('addOrUpdateExpenseTypeAjax', [ExpenseTypeController::class, 'addOrUpdateExpenseTypeAjax'])->name('addOrUpdateExpenseTypeAjax');
      Route::get('getExpenseTypeAjax/{id}', [ExpenseTypeController::class, 'getExpenseTypeAjax'])->name('getExpenseTypeAjax');
      Route::delete('deleteExpenseTypeAjax/{id}', [ExpenseTypeController::class, 'deleteExpenseTypeAjax'])->name('deleteExpenseTypeAjax');
      Route::post('changeStatus/{id}', [ExpenseTypeController::class, 'changeStatus'])->name('changeStatus');
      Route::get('getCodeAjax', [ExpenseTypeController::class, 'getCodeAjax'])->name('getCodeAjax');
      Route::get('view/{id}', [ExpenseTypeController::class, 'view'])->name('view');
      Route::post('addOrUpdateRule', [ExpenseTypeController::class, 'addOrUpdateRule'])->name('addOrUpdateRule');
      Route::delete('deleteRule/{id}', [ExpenseTypeController::class, 'deleteRule'])->name('deleteRule');
      Route::get('checkCodeValidationAjax', [ExpenseTypeController::class, 'checkCodeValidationAjax'])->name('checkCodeValidationAjax');
    });

    // Document Types
    Route::prefix('documenttypes')->name('documenttypes.')->group(function () {
      Route::get('', [DocumentTypeController::class, 'index'])->name('index');
      Route::get('getDocumentTypesAjax', [DocumentTypeController::class, 'getDocumentTypesAjax'])->name('getDocumentTypesAjax');
      Route::post('addOrUpdateDocumentTypeAjax', [DocumentTypeController::class, 'addOrUpdateDocumentTypeAjax'])->name('addOrUpdateDocumentTypeAjax');
      Route::get('getDocumentTypeAjax/{id}', [DocumentTypeController::class, 'getDocumentTypeAjax'])->name('getDocumentTypeAjax');
      Route::delete('deleteDocumentTypeAjax/{id}', [DocumentTypeController::class, 'deleteDocumentTypeAjax'])->name('deleteDocumentTypeAjax');
      Route::post('changeStatus/{id}', [DocumentTypeController::class, 'changeStatus'])->name('changeStatus');
    });

    // IP Groups
    Route::prefix('ipgroup')->name('ipgroup.')->group(function () {
      Route::get('', [IpGroupController::class, 'index'])->name('index');
      Route::get('indexAjax', [IpGroupController::class, 'indexAjax'])->name('indexAjax');
    });

    // Geofence Groups
    Route::prefix('geofencegroup')->name('geofencegroup.')->group(function () {
      Route::get('', [GeofenceGroupController::class, 'index'])->name('index');
      Route::get('indexAjax', [GeofenceGroupController::class, 'indexAjax'])->name('indexAjax');
    });

    // QR Code Groups
    Route::prefix('qrcode')->name('qrcode.')->group(function () {
      Route::get('', [QrGroupController::class, 'index'])->name('index');
      Route::get('indexAjax', [QrGroupController::class, 'indexAjax'])->name('indexAjax');
    });

    // Units (formerly Site Attendance)
    Route::prefix('units')->name('units.')->group(function () {
      Route::get('', [UnitController::class, 'index'])->name('index');
      Route::get('indexAjax', [UnitController::class, 'indexAjax'])->name('indexAjax');
      Route::get('getByIdAjax/{id}', [UnitController::class, 'getByIdAjax'])->name('getByIdAjax');
      Route::post('addOrUpdateAjax', [UnitController::class, 'addOrUpdateAjax'])->name('addOrUpdateAjax');
      Route::delete('deleteAjax/{id}', [UnitController::class, 'deleteAjax'])->name('deleteAjax');
    });

    // Unit Leave Policies
    Route::prefix('unit-leave-policies')->name('unitLeavePolicies.')->group(function () {
      Route::get('forUnit/{siteId}', [\App\Http\Controllers\tenant\UnitLeavePolicyController::class, 'getPoliciesForUnit'])->name('forUnit');
      Route::post('save', [\App\Http\Controllers\tenant\UnitLeavePolicyController::class, 'savePolicy'])->name('save');
    });


    // --- OPERATIONS & REQUESTS ---

    // Attendance
    Route::prefix('attendance')->name('attendance.')->group(function () {
      Route::get('', [AttendanceController::class, 'index'])->name('index');
      Route::get('indexAjax', [AttendanceController::class, 'indexAjax'])->name('indexAjax');
      Route::get('registryAjax', [AttendanceController::class, 'registryAjax'])->name('registryAjax');
      Route::post('import', [AttendanceImportController::class, 'import'])->name('import');
      Route::get('download-sample', [AttendanceImportController::class, 'downloadSample'])->name('download-sample');
      Route::get('chart-ajax', [AttendanceController::class, 'chartAjax'])->name('chart-ajax');
      Route::get('{id}/edit', [AttendanceController::class, 'editAjax'])->name('edit');
      Route::post('{id}/update', [AttendanceController::class, 'updateAjax'])->name('update');

      // Biometric Import Flow
      Route::get('biometric-import', [AttendanceImportController::class, 'showBiometricImport'])->name('biometric-import');
      Route::post('biometric-import/preview', [AttendanceImportController::class, 'previewBiometricImport'])->name('biometric-import.preview');
      Route::post('biometric-import/store', [AttendanceImportController::class, 'storeBiometricImport'])->name('biometric-import.store');
    });

    // Visits
    Route::prefix('visits')->name('visits.')->group(function () {
      Route::get('', [VisitController::class, 'index'])->name('index');
      Route::get('getListAjax', [VisitController::class, 'getListAjax'])->name('getListAjax');
      Route::delete('deleteVisitAjax/{id}', [VisitController::class, 'deleteVisitAjax'])->name('deleteVisitAjax');
      Route::get('getByIdAjax/{id}', [VisitController::class, 'getByIdAjax'])->name('getByIdAjax');
    });

    // Leave Requests
    Route::prefix('leaveRequests')->name('leaveRequests.')->group(function () {
      Route::get('', [LeaveController::class, 'index'])->name('index');
      Route::get('getListAjax', [LeaveController::class, 'getListAjax'])->name('getListAjax');
      Route::post('actionAjax', [LeaveController::class, 'actionAjax'])->name('actionAjax');
      Route::post('bulkActionAjax', [LeaveController::class, 'bulkActionAjax'])->name('bulkActionAjax');
      Route::get('getByIdAjax/{id}', [LeaveController::class, 'getByIdAjax'])->name('getByIdAjax');
    });

    // Expense Requests
    Route::prefix('expenseRequests')->name('expenseRequests.')->group(function () {
      Route::get('', [ExpenseController::class, 'index'])->name('index');
      Route::get('indexAjax', [ExpenseController::class, 'indexAjax'])->name('indexAjax');
      Route::get('getByIdAjax/{id}', [ExpenseController::class, 'getByIdAjax'])->name('getByIdAjax');
      Route::post('actionAjax', [ExpenseController::class, 'actionAjax'])->name('actionAjax');
    });

    // Document Management
    Route::prefix('documentmanagement')->name('documentmanagement.')->group(function () {
      Route::get('', [DocumentRequestController::class, 'index'])->name('index');
      Route::get('getListAjax', [DocumentRequestController::class, 'getListAjax'])->name('getListAjax');
      Route::post('actionAjax', [DocumentRequestController::class, 'actionAjax'])->name('actionAjax');
      Route::get('getByIdAjax/{id}', [DocumentRequestController::class, 'getByIdAjax'])->name('getByIdAjax');
    });

    // Loan Requests
    Route::prefix('loan')->name('loan.')->group(function () {
      Route::get('', [LoanRequestController::class, 'index'])->name('index');
      Route::get('getListAjax', [LoanRequestController::class, 'getListAjax'])->name('getListAjax');
      Route::post('actionAjax', [LoanRequestController::class, 'actionAjax'])->name('actionAjax');
      Route::get('getByIdAjax/{id}', [LoanRequestController::class, 'getByIdAjax'])->name('getByIdAjax');
    });

    // Task Management
    Route::prefix('tasks')->name('tasks.')->group(function () {
      Route::post('store', [TaskController::class, 'store'])->name('store');
      Route::post('update/{id}', [TaskController::class, 'update'])->name('update');
      Route::post('updateStatus/{id}', [TaskController::class, 'updateStatus'])->name('updateStatus');
      Route::delete('delete/{id}', [TaskController::class, 'destroy'])->name('destroy');
    });


    // --- HR & ORGANIZATION ---
    Route::get('debug-onboarding-data', function() {
        return [
           'roles' => \App\Models\Role::get(),
           'departments' => \App\Models\Department::get(),
           'designations' => \App\Models\Designation::get(),
           'auth_tenant' => auth()->user()->tenant_id ?? 'no auth'
        ];
    });

    // --- APPROVALS ---
    Route::middleware(['role:admin|hr'])->group(function () {
      Route::get('approvals', [ApprovalController::class, 'index'])->name('approvals.index');
      Route::post('approvals/profile/{id}/approve', [ApprovalController::class, 'approveProfile'])->name('approvals.profile.approve');
      Route::post('approvals/profile/{id}/reject', [ApprovalController::class, 'rejectProfile'])->name('approvals.profile.reject');
      Route::post('approvals/document/{id}/approve', [ApprovalController::class, 'approveDocument'])->name('approvals.document.approve');
      Route::post('approvals/document/{id}/reject', [ApprovalController::class, 'rejectDocument'])->name('approvals.document.reject');
    });

    // Employees
    Route::prefix('employees')->group(function () {
      Route::name('employees.')->group(function() {
        Route::get('', [EmployeeController::class, 'index'])->name('index');
        Route::get('view/{id}', [EmployeeController::class, 'show'])->name('show');
        Route::match(['get', 'post'], 'indexAjax', [EmployeeController::class, 'userListAjax'])->name('indexAjax');
        Route::post('reset-password', [EmployeeController::class, 'resetPasswordAjax'])->name('resetPasswordAjax');
        Route::post('unlockSecurityAjax', [EmployeeController::class, 'unlockSecurityAjax'])->name('unlockSecurityAjax');
        Route::get('delete', [EmployeeController::class, 'deleteEmployeeAjax'])->name('deleteAjax');
        Route::get('create', [EmployeeController::class, 'create'])->name('create');
        Route::get('getNewEmployeeCode/{locationId}', [EmployeeController::class, 'GetNewEmployeeCodeByLocationAjax'])->name('getNewEmployeeCode');
        Route::get('checkEmailValidationAjax', [EmployeeController::class, 'checkEmailValidationAjax'])->name('checkEmailValidationAjax');
        Route::get('checkPhoneValidationAjax', [EmployeeController::class, 'checkPhoneValidationAjax'])->name('checkPhoneValidationAjax');
        Route::get('checkEmployeeCodeValidationAjax', [EmployeeController::class, 'checkEmployeeCodeValidationAjax'])->name('checkEmployeeCodeValidationAjax');
        Route::delete('deleteEmployeeAjax/{id}', [EmployeeController::class, 'deleteEmployeeAjax'])->name('deleteEmployeeAjax');
        Route::post('store', [EmployeeController::class, 'store'])->name('store');
        Route::post('changeEmployeeProfilePicture', [EmployeeController::class, 'changeEmployeeProfilePicture'])->name('changeEmployeeProfilePicture');
        Route::post('addHrLocation', [EmployeeController::class, 'addHrLocation'])->name('addHrLocation');
        Route::delete('deleteHrLocation/{id}', [EmployeeController::class, 'deleteHrLocation'])->name('deleteHrLocation');
        Route::post('addOrUpdateBankAccount', [EmployeeController::class, 'addOrUpdateBankAccount'])->name('addOrUpdateBankAccount');
        Route::redirect('addOrUpdateBankAccount', '/employees')->name('addOrUpdateBankAccount.get');
        Route::post('addOrUpdateLeaveCount', [EmployeeController::class, 'addOrUpdateLeaveCount'])->name('addOrUpdateLeaveCount');
        Route::post('addOrUpdateDocument', [EmployeeController::class, 'addOrUpdateDocument'])->name('addOrUpdateDocument');
        Route::get('getUserDocumentsAjax/{userId}', [EmployeeController::class, 'getUserDocumentsAjax'])->name('getUserDocumentsAjax');
        Route::get('downloadUserDocument/{userDocumentId}', [EmployeeController::class, 'downloadUserDocument'])->name('downloadUserDocument');
        Route::post('updateBasicInfo', [EmployeeController::class, 'updateBasicInfo'])->name('updateBasicInfo');
        Route::redirect('updateBasicInfo', '/employees')->name('updateBasicInfo.get');
        Route::post('updateCompensationInfo', [EmployeeController::class, 'updateCompensationInfo'])->name('updateCompensationInfo');
        Route::post('updateWorkInformation', [EmployeeController::class, 'updateWorkInformation'])->name('updateWorkInformation');
        Route::post('updateEmergencyContactInfo', [EmployeeController::class, 'updateEmergencyContactInfo'])->name('updateEmergencyContactInfo');
        Route::post('addOrUpdatePayrollAdjustment', [EmployeeController::class, 'addOrUpdatePayrollAdjustment'])->name('addOrUpdatePayrollAdjustment');
        Route::delete('deletePayrollAdjustment/{id}', [EmployeeController::class, 'deletePayrollAdjustment'])->name('deletePayrollAdjustment');
        Route::get('getPayrollAdjustmentAjax/{id}', [EmployeeController::class, 'getPayrollAdjustmentAjax'])->name('getPayrollAdjustmentAjax');
        Route::get('getReportingToUsersAjax', [EmployeeController::class, 'getReportingToUsersAjax'])->name('getReportingToUsersAjax');
        Route::post('removeDevice', [EmployeeController::class, 'removeDevice'])->name('removeDevice');
        Route::post('allotDevice', [EmployeeController::class, 'allotDevice'])->name('allotDevice');
        Route::post('addOrUpdateSalesTarget', [EmployeeController::class, 'addOrUpdateSalesTarget'])->name('addOrUpdateSalesTarget');
        Route::post('storeSalesTarget', [EmployeeController::class, 'addOrUpdateSalesTarget'])->name('storeSalesTarget');
        Route::delete('destroySalesTarget/{id}', [EmployeeController::class, 'destroySalesTarget'])->name('destroySalesTarget');
        Route::get('getTargetByIdAjax/{id}', [EmployeeController::class, 'getTargetByIdAjax'])->name('getTargetByIdAjax');
        Route::post('updateKpiStatus/{id}', [EmployeeController::class, 'updateKpiStatus'])->name('updateKpiStatus');
        Route::post('submitKpiSelfAssessment/{id}', [EmployeeController::class, 'submitKpiSelfAssessment'])->name('submitKpiSelfAssessment');
        Route::post('toggleStatus/{id}', [EmployeeController::class, 'toggleStatus'])->name('toggleStatus');
        Route::post('relieve/{id}', [EmployeeController::class, 'relieveEmployee'])->name('relieve');
        Route::post('retire/{id}', [EmployeeController::class, 'retireEmployee'])->name('retire');
        Route::post('/{user}/terminate', [EmployeeController::class, 'initiateTermination'])->name('terminate');
        Route::post('/{user}/confirmProbation', [EmployeeController::class, 'confirmProbation'])->name('confirmProbation');
        Route::post('/{user}/extendProbation', [EmployeeController::class, 'extendProbation'])->name('extendProbation');
        Route::post('/{user}/failProbation', [EmployeeController::class, 'failProbation'])->name('failProbation');
        Route::post('initiateOnboarding', [EmployeeController::class, 'initiateOnboarding'])->name('initiateOnboarding');
        Route::post('bulk-import-onboarding', [EmployeeController::class, 'validateBulkImport'])->name('bulkImportOnboarding');
        Route::post('validate-bulk-onboarding', [EmployeeController::class, 'validateBulkImport'])->name('validateBulkOnboarding');
        Route::post('process-bulk-onboarding', [EmployeeController::class, 'processBulkImport'])->name('processBulkOnboarding');
        Route::get('bulk-export-onboarding', [EmployeeController::class, 'exportEmployees'])->name('bulkExportOnboarding');
        Route::get('download-onboarding-template', [EmployeeController::class, 'downloadImportTemplate'])->name('downloadOnboardingTemplate');
      // Dedicated Onboarding Review Center routes
      Route::prefix('onboarding')->name('onboarding.')->group(function() {
          Route::get('review-center', [EmployeeController::class, 'reviewCenter'])->name('review_center');
          Route::get('review/{id}', [EmployeeController::class, 'reviewOnboarding'])->name('review');
          Route::post('approve/{id}', [EmployeeController::class, 'approveOnboarding'])->name('approve');
          Route::post('resubmit/{id}', [EmployeeController::class, 'requestResubmission'])->name('resubmit');
      });

      });
      
      // Singular named routes for dropdowns and profile
      Route::name('employee.')->group(function() {
        Route::get('getGeofenceGroups', [EmployeeController::class, 'getGeofenceGroups'])->name('getGeofenceGroups');
        Route::get('getIpGroups', [EmployeeController::class, 'getIpGroups'])->name('getIpGroups');
        Route::get('getQrGroups', [EmployeeController::class, 'getQrGroups'])->name('getQrGroups');
        Route::get('getSites', [EmployeeController::class, 'getSites'])->name('getSites');
        Route::get('getDynamicQrDevices', [EmployeeController::class, 'getDynamicQrDevices'])->name('getDynamicQrDevices');
        Route::get('myProfile', [EmployeeController::class, 'myProfile'])->name('myProfile');
        Route::post('update-my-profile', [EmployeeController::class, 'updateMyProfile'])->name('updateMyProfile');
      });
    });

    // Employee Lifecycle (HR/Admin)
    Route::middleware(['role:hr|Admin|admin'])->prefix('employee-lifecycle')->name('employee-lifecycle.')->group(function () {
      Route::get('', [EmployeeLifecycleController::class, 'index'])->name('index');
      Route::get('promotions', [EmployeeLifecycleController::class, 'promotions'])->name('promotions');
      Route::get('transfers', [EmployeeLifecycleController::class, 'transfers'])->name('transfers');
      Route::get('warnings', [EmployeeLifecycleController::class, 'warnings'])->name('warnings');
      Route::get('resignations', [EmployeeLifecycleController::class, 'resignations'])->name('resignations');
      Route::get('terminations', [EmployeeLifecycleController::class, 'terminations'])->name('terminations');
      Route::get('complaints', [EmployeeLifecycleController::class, 'complaints'])->name('complaints');
      Route::post('complaints/store', [EmployeeLifecycleController::class, 'storeComplaint'])->name('complaints.store');
      Route::get('trips', [EmployeeLifecycleController::class, 'trips'])->name('trips');
      Route::post('trips/store', [EmployeeLifecycleController::class, 'storeTrip'])->name('trips.store');
      Route::get('announcements', [EmployeeLifecycleController::class, 'announcements'])->name('announcements');
      Route::post('announcements/store', [EmployeeLifecycleController::class, 'storeAnnouncement'])->name('announcements.store');
    });
    
    // Approvals (HR/Admin)
    Route::middleware(['role:hr|Admin|admin'])->prefix('approvals')->name('approvals.')->group(function () {
      Route::get('', [ApprovalController::class, 'index'])->name('index');
      Route::post('profile/{id}/approve', [ApprovalController::class, 'approveProfile'])->name('profile.approve');
      Route::post('profile/{id}/reject', [ApprovalController::class, 'rejectProfile'])->name('profile.reject');
      Route::post('document/{id}/approve', [ApprovalController::class, 'approveDocument'])->name('document.approve');
      Route::post('document/{id}/reject', [ApprovalController::class, 'rejectDocument'])->name('document.reject');
    });

    // Departments
    Route::prefix('departments')->name('departments.')->group(function () {
      Route::get('', [DepartmentsController::class, 'index'])->name('index');
      Route::get('indexAjax', [DepartmentsController::class, 'indexAjax'])->name('indexAjax');
      Route::post('addOrUpdateDepartmentAjax', [DepartmentsController::class, 'addOrUpdateDepartmentAjax'])->name('addOrUpdateDepartmentAjax');
      Route::get('getDepartmentListAjax', [DepartmentsController::class, 'getListAjax'])->name('getListAjax');
      Route::get('getParentDepartments', [DepartmentsController::class, 'getParentDepartments'])->name('getParentDepartments');
      Route::get('getDepartmentAjax/{id}', [DepartmentsController::class, 'getDepartmentAjax'])->name('getDepartmentAjax');
      Route::get('getDepartmentUsersAjax/{id?}', [DepartmentsController::class, 'getDepartmentUsersAjax']);
      Route::delete('deleteAjax/{id}', [DepartmentsController::class, 'deleteAjax'])->name('deleteAjax');
      Route::post('changeStatus/{id}', [DepartmentsController::class, 'changeStatus'])->name('changeStatus');
    });

    // Designations
    Route::prefix('designations')->name('designations.')->group(function () {
      Route::get('', [DesignationController::class, 'index'])->name('index');
      Route::get('indexAjax', [DesignationController::class, 'indexAjax'])->name('indexAjax');
      Route::get('getDesignationListAjax', [DesignationController::class, 'getDesignationListAjax'])->name('getDesignationListAjax');
      Route::post('addOrUpdateAjax', [DesignationController::class, 'addOrUpdateAjax'])->name('addOrUpdateAjax');
      Route::get('getByIdAjax/{id}', [DesignationController::class, 'getByIdAjax'])->name('getByIdAjax');
      Route::delete('deleteAjax/{id}', [DesignationController::class, 'deleteAjax'])->name('deleteAjax');
      Route::post('changeStatus/{id}', [DesignationController::class, 'changeStatus'])->name('changeStatus');
      Route::get('checkCodeValidationAjax', [DesignationController::class, 'checkCodeValidationAjax'])->name('checkCodeValidationAjax');
    });

    // Teams
    Route::prefix('teams')->name('teams.')->group(function () {
      Route::get('', [TeamController::class, 'index'])->name('index');
      Route::get('getTeamsListAjax', [TeamController::class, 'getTeamsListAjax'])->name('getTeamsListAjax');
      Route::post('addOrUpdateTeamAjax', [TeamController::class, 'addOrUpdateTeamAjax'])->name('addOrUpdateTeamAjax');
      Route::get('getTeamAjax/{id}', [TeamController::class, 'getTeamAjax'])->name('getTeamAjax');
      Route::delete('deleteTeamAjax/{id}', [TeamController::class, 'deleteTeamAjax'])->name('deleteTeamAjax');
      Route::post('changeStatus/{id}', [TeamController::class, 'changeStatus'])->name('changeStatus');
      Route::get('getCodeAjax', [TeamController::class, 'getCodeAjax'])->name('getCodeAjax');
      Route::get('getTeamListAjax', [TeamController::class, 'getTeamListAjax'])->name('getTeamListAjax');
      Route::get('checkCodeValidationAjax', [TeamController::class, 'checkCodeValidationAjax'])->name('checkCodeValidationAjax');
    });

    // Organization Hierarchy already moved to universal group for accessibility


    // --- ASSETS ---

    // Asset Management (Admin/HR)
    Route::middleware(['role:hr|Admin|admin|manager'])->prefix('asset-management')->name('assets.')->group(function () {
      Route::get('', [AssetController::class, 'index'])->name('index');
      Route::get('list-ajax', [AssetController::class, 'getListAjax'])->name('listAjax');
      Route::get('getAssetAjax/{id}', [AssetController::class, 'getAssetAjax'])->name('getAssetAjax');
      Route::get('create', [AssetController::class, 'create'])->name('create');
      Route::get('{id}', [AssetController::class, 'show'])->name('show');
      Route::get('{id}/edit', [AssetController::class, 'edit'])->name('edit');
      Route::post('store', [AssetController::class, 'store'])->name('store');
      Route::put('{id}', [AssetController::class, 'update'])->name('update');
      Route::delete('{id}', [AssetController::class, 'destroy'])->name('destroy');
      Route::post('assign/{id}', [AssetController::class, 'assignAsset'])->name('assign');
      Route::post('unassign/{id}', [AssetController::class, 'unassignAsset'])->name('unassign');
    });

    // --- RECRUITMENT MANAGEMENT ---
    Route::resource('job', JobController::class);
    Route::post('job/copy/{id}', [JobController::class, 'copyjob'])->name('job.copy');

    Route::resource('job-category', JobCategoryController::class);
    Route::resource('job-stage', JobStageController::class);
    Route::post('job-stage/order', [JobStageController::class, 'order'])->name('job.stage.order');

    Route::resource('job-application', JobApplicationController::class);
    Route::post('job-application/order', [JobApplicationController::class, 'order'])->name('job.application.order');
    Route::get('candidates', [JobApplicationController::class, 'candidate'])->name('job.application.candidate');
    Route::delete('job-application/archive/{id}', [JobApplicationController::class, 'archive'])->name('job.application.archive');
    Route::post('job-application/add-note/{id}', [JobApplicationController::class, 'addNote'])->name('job.application.addNote');
    Route::delete('job-application/delete-note/{id}', [JobApplicationController::class, 'destroyNote'])->name('job.application.deleteNote');
    Route::post('job-application/add-skill/{id}', [JobApplicationController::class, 'addSkill'])->name('job.application.addSkill');
    Route::post('job-application/add-experience/{id}', [JobApplicationController::class, 'addExperience'])->name('job.application.addExperience');
    Route::post('job-application/get-by-job', [JobApplicationController::class, 'getByJob'])->name('get.job.application');

    Route::resource('custom-question', CustomQuestionController::class);

    Route::get('interview-schedule/data', [InterviewScheduleController::class, 'get_interview_schedule_data'])->name('interview-schedule.data');
    Route::resource('interview-schedule', InterviewScheduleController::class)->except(['create']);
    Route::get('interview-schedule/create/{candidate?}', [InterviewScheduleController::class, 'create'])->name('interview-schedule.create');

    }); // Close the admin|hr|manager group starting at line 108

    // --- GENERAL AUTHENTICATED ROUTES (Employee & Above) ---
    Route::middleware(['web', 'auth'])->group(function() {

        // Digital Library - Viewable by all authenticated users
        Route::prefix('digital-library')->name('library.')->group(function() {
            Route::get('/', [DigitalLibraryController::class, 'index'])->name('index');
            Route::get('/access/{id}', [DigitalLibraryController::class, 'access'])->name('access');
            
            // AI Chat & Uploads (Admin/HR Only)
            Route::middleware(['role:admin|hr'])->group(function() {
                Route::post('chat', [DigitalLibraryController::class, 'chat'])->name('chat');
                Route::post('store', [DigitalLibraryController::class, 'store'])->name('store');
                Route::post('analyze', [DigitalLibraryController::class, 'analyze'])->name('analyze');
                Route::post('bulk-store', [DigitalLibraryController::class, 'bulkStore'])->name('bulk-store');
            });
        });

        // AI Training (Admin/HR Only)
        Route::middleware(['role:admin|hr', 'can:ai.training.manage'])->prefix('ai-training')->name('ai-training.')->group(function() {
            Route::get('/', [AiTrainingController::class, 'index'])->name('index');
            Route::post('/store', [AiTrainingController::class, 'store'])->name('store');
            Route::post('/update-instructions', [AiTrainingController::class, 'updateInstructions'])->name('update-instructions');
            Route::delete('/destroy/{id}', [AiTrainingController::class, 'destroy'])->name('destroy');
        });

        // Employee Profile & Self-Update Routes
        Route::prefix('employees')->group(function () {
          Route::name('employee.')->group(function() {
            Route::get('myProfile', [EmployeeController::class, 'myProfile'])->name('myProfile');
            Route::get('celebrations', [EmployeeController::class, 'celebrations'])->name('celebrations');
            Route::post('changeEmployeeProfilePicture', [EmployeeController::class, 'changeEmployeeProfilePicture'])->name('changeEmployeeProfilePicture');
          });

          Route::name('employees.')->group(function() {
            Route::post('updateBasicInfo', [EmployeeController::class, 'updateBasicInfo'])->name('updateBasicInfo');
            Route::post('addOrUpdateBankAccount', [EmployeeController::class, 'addOrUpdateBankAccount'])->name('addOrUpdateBankAccount');
            Route::post('addOrUpdateDocument', [EmployeeController::class, 'addOrUpdateDocument'])->name('addOrUpdateDocument');
          });
        });

        // My Assets
        Route::prefix('my-assets')->name('myAssets.')->group(function () {
          Route::get('', [MyAssetsController::class, 'index'])->name('index');
          Route::get('{id}', [MyAssetsController::class, 'show'])->name('show');
          Route::get('getListAjax', [MyAssetsController::class, 'getListAjax'])->name('getListAjax');
          Route::post('request-maintenance/{id}', [MyAssetsController::class, 'requestMaintenance'])->name('requestMaintenance');
        });

        // Organization Hierarchy - Viewable by all authenticated users
        Route::prefix('organizationHierarchy')->name('organizationHierarchy.')->group(function () {
          Route::get('', [OrganisationHierarchyController::class, 'index'])->name('index');
        });
    });

    // --- RE-OPEN ADMINISTRATIVE ROUTES (Admin/HR/Manager) ---
    Route::middleware([
      'web',
      'auth',
      'role:Admin|Admin|admin|hr|manager'
    ])->group(function () {


    // Asset Categories (Admin/HR)
    Route::middleware(['role:Admin|Admin|admin|hr|manager'])->prefix('asset-categories')->name('assetCategories.')->group(function () {
        Route::get('', [AssetCategoryController::class, 'index'])->name('index');
        Route::get('list-ajax', [AssetCategoryController::class, 'getListAjax'])->name('listAjax');
        Route::post('', [AssetCategoryController::class, 'store'])->name('store');
        Route::get('{id}/edit', [AssetCategoryController::class, 'edit'])->name('edit');
        Route::put('{id}', [AssetCategoryController::class, 'update'])->name('update');
        Route::delete('{id}', [AssetCategoryController::class, 'destroy'])->name('destroy');
    });


    /* --- CLIENTS & VISITS DISABLED ---
    Route::prefix('clients')->name('client.')->group(function () {
      Route::get('', [ClientController::class, 'index'])->name('index');
      Route::get('show/{id}', [ClientController::class, 'show'])->name('show');
      Route::get('create', [ClientController::class, 'create'])->name('create');
      Route::post('store', [ClientController::class, 'store'])->name('store');
      Route::get('edit/{id}', [Clientcontroller::class, 'edit'])->name('edit');
      Route::post('update/{id}', [Clientcontroller::class, 'update'])->name('update');
      Route::post('changeStatus', [Clientcontroller::class, 'changeStatus'])->name('changeStatus');
      Route::delete('destroy/{id}', [Clientcontroller::class, 'destroy'])->name('destroy');
    });
    */


    // --- ROLES & PERMISSIONS ---
    Route::get('roles', [RoleController::class, 'index'])->name('roles.index');
    Route::post('roles/addOrUpdateAjax', [RoleController::class, 'addOrUpdateAjax'])->name('roles.addOrUpdateAjax');
    Route::delete('roles/deleteAjax/{id}', [RoleController::class, 'deleteAjax'])->name('roles.deleteAjax');
    Route::get('permissions', [PermissionController::class, 'index'])->name('permissions.index');
    
    // User-specific special permissions
    Route::get('roles/getUserPermissionsAjax/{userId}', [RoleController::class, 'getUserPermissionsAjax'])->name('roles.getUserPermissionsAjax');
    Route::post('roles/syncUserPermissionsAjax', [RoleController::class, 'syncUserPermissionsAjax'])->name('roles.syncUserPermissionsAjax');


    // --- SYSTEM UTILITIES ---

    // Audit Logs
    Route::prefix('auditLogs')->name('auditLogs.')->group(function () {
      Route::get('', [AuditLogController::class, 'index'])->name('index');
      Route::get('show/{id}', [AuditLogController::class, 'show'])->name('show');
    });

    // Device Status & Biometric Devices
    Route::prefix('device')->name('device.')->group(function () {
      Route::get('', [DeviceController::class, 'index'])->name('index');
      Route::get('indexAjax', [DeviceController::class, 'indexAjax'])->name('indexAjax');
      Route::get('getByIdAjax/{id}', [DeviceController::class, 'getByIdAjax'])->name('getByIdAjax');
      Route::delete('deleteAjax/{id}', [DeviceController::class, 'deleteAjax'])->name('deleteAjax');
    });

    Route::prefix('biometric')->name('biometric.')->group(function () {
      Route::get('', [BiometricDeviceController::class, 'index'])->name('index');
      Route::get('create', [BiometricDeviceController::class, 'create'])->name('create');
      Route::post('store', [BiometricDeviceController::class, 'store'])->name('store');
      Route::get('{biometric}/edit', [BiometricDeviceController::class, 'edit'])->name('edit');
      Route::put('{biometric}', [BiometricDeviceController::class, 'update'])->name('update');
      Route::delete('{biometric}', [BiometricDeviceController::class, 'destroy'])->name('destroy');
      Route::post('test-connection', [BiometricDeviceController::class, 'testConnection'])->name('test-connection');
    });

    // SOS (Addon Check)
    Route::middleware([SOSAddonCheck::class])->group(function () {
      Route::get('/sos', [SOSController::class, 'index'])->name('sos.index');
      Route::get('/sos/fetch', [SOSController::class, 'fetchSOSRequests'])->name('sos.fetch');
      Route::post('/sos/resolve/{id}', [SOSController::class, 'markAsResolved'])->name('sos.resolve');
    });

    // Admin Chat
    Route::middleware(['role:admin|hr'])->get('/admin/chat', [\App\Http\Controllers\tenant\ChatController::class, 'index'])->name('chat.index');

    // Account Management
    Route::prefix('account')->name('account.')->group(function () {
      Route::get('', [AccountController::class, 'index'])->name('index');
      Route::get('activeInactiveUserAjax/{id}', [AccountController::class, 'activeInactiveUserAjax'])->name('activeInactiveUserAjax');
      Route::get('suspendUserAjax/{id}', [AccountController::class, 'suspendUserAjax'])->name('suspendUserAjax');
      Route::get('deleteUserAjax/{id}', [AccountController::class, 'deleteUserAjax'])->name('deleteUserAjaxTenant');
      Route::get('viewUser/{id}', [AccountController::class, 'viewUser'])->name('viewUser');
      Route::get('indexAjax', [AccountController::class, 'userListAjax'])->name('userListAjax');
      Route::get('getRolesAjax', [AccountController::class, 'getRolesAjax'])->name('getRolesAjax');
      Route::get('getUsersAjax', [AccountController::class, 'getUsersAjax'])->name('getUsersAjax');
      Route::get('getUsersByRoleAjax/{role}', [AccountController::class, 'getUsersByRoleAjax'])->name('getUsersByRoleAjax');
      Route::post('addOrUpdateUserAjax', [AccountController::class, 'addOrUpdateUserAjax'])->name('addOrUpdateUserAjax');
      Route::get('editUserAjax/{id}', [AccountController::class, 'editUserAjax'])->name('editUserAjax');
      Route::post('updateUserAjax/{id}', [AccountController::class, 'updateUserAjax'])->name('updateUserAjax');
      Route::post('updateUserStatusAjax/{id}', [AccountController::class, 'updateUserStatusAjax'])->name('updateUserStatusAjax');
      Route::post('changeUserStatusAjax/{id}', [AccountController::class, 'changeUserStatusAjax'])->name('changeUserStatusAjax');
      Route::post('changePassword', [AccountController::class, 'changePassword'])->name('changePassword');
    });

});


