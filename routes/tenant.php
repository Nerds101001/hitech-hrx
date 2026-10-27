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
use App\Http\Controllers\tenant\BirthdayController;
use App\Http\Controllers\tenant\DepartmentsController;
use App\Http\Controllers\tenant\DesignationController;
use App\Http\Controllers\tenant\DeviceController;
use App\Http\Controllers\tenant\DocumentRequestController;
use App\Http\Controllers\tenant\DocumentTypeController;
use App\Http\Controllers\tenant\SetuMappingController;
use App\Http\Controllers\tenant\SetuSyncController;
use App\Http\Controllers\tenant\CrmMappingSyncController;
use App\Http\Controllers\tenant\EmployeeController;
use App\Http\Controllers\tenant\EmployeeLifecycleController;
use App\Http\Controllers\tenant\ExpenseController;
use App\Http\Controllers\tenant\ExpenseTypeController;
use App\Http\Controllers\tenant\GeofenceGroupController;
use App\Http\Controllers\tenant\AnnouncementController;
use App\Http\Controllers\tenant\HolidayController;
use App\Http\Controllers\tenant\IpGroupController;
use App\Http\Controllers\tenant\LeaveController;
use App\Http\Controllers\tenant\LeaveTypeController;
use App\Http\Controllers\tenant\LeavePolicyProfileController;
use App\Http\Controllers\tenant\LoanRequestController;
use App\Http\Controllers\tenant\MyAssetsController;
use App\Http\Controllers\tenant\UniformRequestController;
use App\Http\Controllers\tenant\OrganisationHierarchyController;
use App\Http\Controllers\tenant\PayrollController;
use App\Http\Controllers\tenant\SalaryImportController;
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
use App\Http\Controllers\tenant\HRPolicyController;
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
  Route::get('/auth/login', [AuthController::class, 'login'])->name('auth.login');
  Route::post('/auth/login', [AuthController::class, 'loginPost'])->name('auth.loginPost');


  // --- PUBLIC RECRUITMENT ROUTES ---
  Route::get('/sales-visits/survey/{token}', [\App\Http\Controllers\tenant\SalesVisitController::class, 'showSurvey'])->name('sales-visits.survey');
  Route::post('/sales-visits/survey/{token}', [\App\Http\Controllers\tenant\SalesVisitController::class, 'submitSurvey'])->name('sales-visits.survey.submit');
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

  // Browser push (standard Web Push / VAPID) -- every authenticated user
  // manages only their own subscriptions.
  Route::middleware(['auth'])->prefix('push')->name('push.')->group(function () {
      Route::post('subscribe', [\App\Http\Controllers\tenant\PushSubscriptionController::class, 'subscribe'])->name('subscribe');
      Route::post('unsubscribe', [\App\Http\Controllers\tenant\PushSubscriptionController::class, 'unsubscribe'])->name('unsubscribe');
  });

  // --- TRAINING PORTAL ROUTES ---
  Route::middleware(['auth'])->prefix('training')->name('training.')->group(function () {
      Route::get('/', [\App\Http\Controllers\tenant\TrainingController::class, 'index'])->name('portal');
      Route::get('/module/{id}', [\App\Http\Controllers\tenant\TrainingController::class, 'showModule'])->name('module.show');
      Route::get('/assessment/{id}', [\App\Http\Controllers\tenant\TrainingController::class, 'getAssessment'])->name('assessment.get');
      Route::post('/assessment/{id}', [\App\Http\Controllers\tenant\TrainingController::class, 'submitAssessment'])->name('assessment.submit');
      Route::get('/video/{id}', [\App\Http\Controllers\tenant\TrainingController::class, 'showLibraryVideo'])->name('library-video.show');
      Route::post('/video/{id}/watched', [\App\Http\Controllers\tenant\TrainingController::class, 'markLibraryVideoWatched'])->name('library-video.watched');
      Route::post('/module/{id}/chat', [\App\Http\Controllers\tenant\TrainingController::class, 'chatWithDocument'])->name('module.chat');
      
      // HR/Admin Activity Dashboard
      Route::get('/activity', [\App\Http\Controllers\tenant\TrainingController::class, 'activityDashboard'])->name('activity');

      // HR/Manager Routes
      Route::get('/report-card/{userId}', [\App\Http\Controllers\tenant\TrainingController::class, 'getReportCard'])->name('report-card');
      Route::post('/approve/{userId}', [\App\Http\Controllers\tenant\TrainingController::class, 'approveTraining'])->name('approve');

      // Management Routes (HR/Admin)
      Route::get('/manage', [\App\Http\Controllers\tenant\TrainingController::class, 'managementIndex'])->name('manage.index');
      Route::post('/manage/phase', [\App\Http\Controllers\tenant\TrainingController::class, 'storePhase'])->name('manage.phase.store');
      Route::delete('/manage/phase/{id}', [\App\Http\Controllers\tenant\TrainingController::class, 'destroyPhase'])->name('manage.phase.destroy');
      Route::post('/manage/module', [\App\Http\Controllers\tenant\TrainingController::class, 'storeModule'])->name('manage.module.store');
      Route::delete('/manage/module/{id}', [\App\Http\Controllers\tenant\TrainingController::class, 'destroyModule'])->name('manage.module.destroy');
      Route::post('/manage/library-videos/order', [\App\Http\Controllers\tenant\TrainingController::class, 'saveLibraryVideoOrder'])->name('manage.library-videos.order');
      Route::post('/manage/module/{id}/generate-ai-questions', [\App\Http\Controllers\tenant\TrainingController::class, 'generateAIQuestions'])->name('manage.module.ai-questions');
      Route::post('/manage/question', [\App\Http\Controllers\tenant\TrainingController::class, 'storeQuestion'])->name('manage.question.store');
      Route::delete('/manage/question/{id}', [\App\Http\Controllers\tenant\TrainingController::class, 'destroyQuestion'])->name('manage.question.destroy');
  });
});

Route::middleware([
  'web',
  'auth',
  'admin.guard',           // Hard gate: employee/manager roles and permission overrides
])->group(function () {

    // --- DASHBOARD & GENERAL ---
    Route::get('/', [DashboardController::class, 'index'])->name('tenant.dashboard');
    Route::get('/dashboard/live-stats', [DashboardController::class, 'liveStats'])->name('dashboard.live-stats');

    // --- ANNOUNCEMENTS ---
    // Publishing is open to admin/HR (any audience) and managers (their own
    // team only — enforced inside the controller). The feed is readable by
    // everyone; the employee-facing route lives in routes/user.php.
    Route::prefix('announcements')->name('announcements.')->group(function () {
      Route::get('feed', [AnnouncementController::class, 'myFeed'])->name('feed');

      // Open to everyone: the controller serves the publishing dashboard to
      // those who can publish and the personal feed to everyone else. The
      // sidebar shows this to all users, so it must never 403.
      Route::get('', [AnnouncementController::class, 'dashboard'])->name('dashboard');

      Route::middleware(['role_or_permission:admin|hr|manager|announcements.manage'])->group(function () {
        Route::get('dashboardAjax', [AnnouncementController::class, 'dashboardAjax'])->name('dashboardAjax');
        Route::post('store', [AnnouncementController::class, 'store'])->name('store');
        Route::get('getByIdAjax/{id}', [AnnouncementController::class, 'getByIdAjax'])->name('getByIdAjax');
        Route::delete('deleteAjax/{id}', [AnnouncementController::class, 'deleteAjax'])->name('deleteAjax');
        Route::post('toggleStatusAjax/{id}', [AnnouncementController::class, 'toggleStatusAjax'])->name('toggleStatusAjax');
      });
    });



    // --- MONITORING --- (Admin/HR/Accounts/Manager by default, matching
    // admin.guard's own set; monitoring.* permissions available to delegate
    // just this to one person)
    Route::middleware(['role_or_permission:admin|hr|accounts|manager|monitoring.live_location|monitoring.card_view|monitoring.timeline'])->group(function () {
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
    });

    Route::get('/lang/{locale}', [LanguageController::class, 'swap']);
    Route::get('/getSearchDataAjax', [BaseController::class, 'getSearchDataAjax'])->name('search.Ajax');

    // --- PROBATION MANAGEMENT ---
    Route::prefix('probation')->name('probation.')->group(function() {
        Route::get('evaluate/{id}', [ProbationController::class, 'showEvaluationForm'])->name('evaluate');
        Route::post('store/{id}', [ProbationController::class, 'storeEvaluation'])->name('store');
        
        // HR Review Routes — probation.view grants read-only access to the
        // dashboard/list/review pages; finalizing stays manage-only.
        Route::group(['middleware' => ['role_or_permission:hr|admin|probation.view|probation.manage']], function() {
            Route::get('dashboard', [ProbationController::class, 'dashboard'])->name('dashboard');
            Route::get('dashboardAjax', [ProbationController::class, 'dashboardAjax'])->name('dashboardAjax');
            Route::get('evaluations', [ProbationController::class, 'index'])->name('index');
            Route::get('review/{id}', [ProbationController::class, 'review'])->name('review');
            Route::post('finalize/{id}', [ProbationController::class, 'finalize'])
                ->middleware('role_or_permission:hr|admin|probation.manage')
                ->name('finalize');
        });
    });


    // --- AUTH ACTIONS ---
    Route::post('/auth/logout', [AuthController::class, 'logout'])->name('auth.logout');

    // --- SETTINGS (ADMIN ONLY) ---
    Route::middleware(['role_or_permission:admin|settings.manage'])->prefix('settings/')->name('settings.')->group(function () {
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



    // --- REPORTS --- (Admin/HR only by explicit decision; reports.view/
    // reports.export are available to delegate to one specific person later)
    Route::middleware(['role_or_permission:admin|hr|reports.view|reports.export'])->group(function () {
      Route::get('reports', [ReportController::class, 'index'])->name('reports.index');
      Route::post('report/getAttendanceReport', [ReportController::class, 'getAttendanceReport'])->name('report.getAttendanceReport');
      Route::post('report/getVisitReport', [ReportController::class, 'getVisitReport'])->name('report.getVisitReport');
      Route::post('report/getLeaveReport', [ReportController::class, 'getLeaveReport'])->name('report.getLeaveReport');
      Route::post('report/getExpenseReport', [ReportController::class, 'getExpenseReport'])->name('report.getExpenseReport');
      Route::post('reports/getProductOrderReport', [ReportController::class, 'getProductOrderReport'])->name('report.getProductOrderReport');
    });

    // --- PAYROLL MANAGEMENT ---
    Route::middleware(['role_or_permission:admin|hr|accounts|payroll.view|payroll.process'])->group(function () {
      Route::get('payroll', [PayrollController::class, 'index'])->name('payroll.index');
      Route::get('payroll/indexAjax', [PayrollController::class, 'indexAjax'])->name('payroll.indexAjax');
      Route::post('payroll/generate', [PayrollController::class, 'generate'])->name('payroll.generate');
      Route::post('payroll/bulkApprove', [PayrollController::class, 'bulkApprove'])->name('payroll.bulkApprove');
      Route::post('payroll/publishSingle/{id}', [PayrollController::class, 'publishSingle'])->name('payroll.publishSingle');
      Route::delete('payroll/destroyAjax/{id}', [PayrollController::class, 'destroyAjax'])->name('payroll.destroyAjax');
      
      // Salary Breakup Import
      Route::post('payroll/salary-import/preview', [SalaryImportController::class, 'preview'])->name('payroll.salary-import.preview');
      Route::post('payroll/salary-import/store', [SalaryImportController::class, 'store'])->name('payroll.salary-import.store');
      Route::get('payroll/salary-import/download-sample', [SalaryImportController::class, 'downloadSample'])->name('payroll.salary-import.download-sample');
    });

    // --- MASTERS --- (explicit gate matching admin.guard's exact current
    // role set — closes the gap where holding one of AdminRoleGuard's 6
    // override permissions alone, with no admin/hr/accounts/manager role,
    // would otherwise reach every module that has no gate of its own)
    Route::middleware(['role_or_permission:admin|hr|accounts|manager|shifts.manage|holidays.manage|leave.create_type|leave.create_policy|expense.manage_types|document_types.manage|ipgroup.manage|geofencegroup.manage|qrgroup.manage|units.manage|setu.manage'])->group(function () {

    // Shifts
    Route::middleware(['role_or_permission:admin|hr|accounts|manager|shifts.manage'])->prefix('shifts')->name('shifts.')->group(function () {
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
    Route::middleware(['role_or_permission:admin|hr|accounts|manager|holidays.manage'])->prefix('holidays')->name('holidays.')->group(function () {
      Route::get('', [HolidayController::class, 'index'])->name('index');
      Route::get('dashboard', [HolidayController::class, 'dashboard'])->name('dashboard');
      Route::get('dashboardAjax', [HolidayController::class, 'dashboardAjax'])->name('dashboardAjax');
      Route::get('indexAjax', [HolidayController::class, 'indexAjax'])->name('indexAjax');
      Route::post('addOrUpdateHolidayAjax', [HolidayController::class, 'addOrUpdateHolidayAjax'])->name('addOrUpdateHolidayAjax');
      Route::get('getByIdAjax/{id}', [HolidayController::class, 'getByIdAjax'])->name('getByIdAjax');
      Route::delete('deleteAjax/{id}', [HolidayController::class, 'deleteAjax'])->name('deleteAjax');
      Route::post('changeStatusAjax/{id}', [HolidayController::class, 'changeStatusAjax'])->name('changeStatusAjax');
    });

    // Leave Types
    Route::middleware(['role_or_permission:admin|hr|accounts|manager|leave.create_type'])->prefix('leaveTypes')->name('leaveTypes.')->group(function () {
      Route::get('', [LeaveTypeController::class, 'index'])->name('index');
      Route::get('getLeaveTypesAjax', [LeaveTypeController::class, 'getLeaveTypesAjax'])->name('getLeaveTypesAjax');
      Route::post('addOrUpdateLeaveTypeAjax', [LeaveTypeController::class, 'addOrUpdateLeaveTypeAjax'])->name('addOrUpdateLeaveTypeAjax');
      Route::get('getLeaveTypeAjax/{id}', [LeaveTypeController::class, 'getLeaveTypeAjax'])->name('getLeaveTypeAjax');
      Route::delete('deleteLeaveTypeAjax/{id}', [LeaveTypeController::class, 'deleteLeaveTypeAjax'])->name('deleteLeaveTypeAjax');
      Route::post('changeStatus/{id}', [LeaveTypeController::class, 'changeStatus'])->name('changeStatus');
      Route::get('getCodeAjax', [LeaveTypeController::class, 'getCodeAjax'])->name('getCodeAjax');
      Route::get('checkCodeValidationAjax', [LeaveTypeController::class, 'checkCodeValidationAjax'])->name('checkCodeValidationAjax');
    });

    // Leave Policy Profiles — admin/HR only. The surrounding group also admits
    // accounts/manager for other masters, so this narrows just this feature.
    Route::middleware(['role_or_permission:admin|hr|leave.create_policy'])->group(function () {
      Route::get('leavePolicyProfiles', [LeavePolicyProfileController::class, 'index'])->name('leavePolicyProfiles.index');
      Route::get('leavePolicyProfiles/getProfileAjax/{id}', [LeavePolicyProfileController::class, 'getProfileAjax'])->name('leavePolicyProfiles.getProfileAjax');
      Route::post('leavePolicyProfiles/addOrUpdateAjax', [LeavePolicyProfileController::class, 'addOrUpdateAjax'])->name('leavePolicyProfiles.addOrUpdateAjax');
      Route::post('leavePolicyProfiles/addManualCreditAjax', [LeavePolicyProfileController::class, 'addManualCreditAjax'])->name('leavePolicyProfiles.addManualCreditAjax');
      Route::get('leavePolicyProfiles/getProfileListAjax', [LeavePolicyProfileController::class, 'getProfileListAjax'])->name('leavePolicyProfiles.getProfileListAjax');
    });

    // Expense Types
    Route::middleware(['role_or_permission:admin|hr|accounts|manager|expense.manage_types'])->prefix('expenseTypes')->name('expenseTypes.')->group(function () {
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
    Route::middleware(['role_or_permission:admin|hr|accounts|manager|document_types.manage'])->prefix('documenttypes')->name('documenttypes.')->group(function () {
      Route::get('', [DocumentTypeController::class, 'index'])->name('index');
      Route::get('getDocumentTypesAjax', [DocumentTypeController::class, 'getDocumentTypesAjax'])->name('getDocumentTypesAjax');
      Route::post('addOrUpdateDocumentTypeAjax', [DocumentTypeController::class, 'addOrUpdateDocumentTypeAjax'])->name('addOrUpdateDocumentTypeAjax');
      Route::get('getDocumentTypeAjax/{id}', [DocumentTypeController::class, 'getDocumentTypeAjax'])->name('getDocumentTypeAjax');
      Route::delete('deleteDocumentTypeAjax/{id}', [DocumentTypeController::class, 'deleteDocumentTypeAjax'])->name('deleteDocumentTypeAjax');
      Route::post('changeStatus/{id}', [DocumentTypeController::class, 'changeStatus'])->name('changeStatus');
    });

    // Setu Salesperson Mappings — Tally ledger name -> HRX user, reviewed
    // and corrected here instead of via the one-off CSV export/import loop.
    // Admin/HR only (or setu.manage): the group around these admits every
    // manager, accounts user and any one config permission, so "Manage
    // Expense Types" alone used to open the Setu pages.
    Route::middleware(['role_or_permission:admin|hr|setu.manage'])->prefix('setu-mappings')->name('setu-mappings.')->group(function () {
      Route::get('', [SetuMappingController::class, 'index'])->name('index');
      Route::get('getListAjax', [SetuMappingController::class, 'getListAjax'])->name('getListAjax');
      Route::get('getByIdAjax/{id}', [SetuMappingController::class, 'getByIdAjax'])->name('getByIdAjax');
      Route::post('addOrUpdateAjax', [SetuMappingController::class, 'addOrUpdateAjax'])->name('addOrUpdateAjax');
    });

    // Sales Group Mapping — supersedes Setu Salesperson Mappings above: maps
    // a Tally LEDGER GROUP (tally_ledgers.ledger_group) to the HRX user it
    // refers to, pulled live from the already-synced local Tally tables
    // instead of an external export. Same admin/HR-only gate.
    Route::middleware(['role_or_permission:admin|hr|setu.manage'])->prefix('sales-group-mappings')->name('sales-group-mappings.')->group(function () {
      Route::get('', [\App\Http\Controllers\tenant\SalesGroupMappingController::class, 'index'])->name('index');
      Route::get('getListAjax', [\App\Http\Controllers\tenant\SalesGroupMappingController::class, 'getListAjax'])->name('getListAjax');
      Route::get('getByIdAjax/{id}', [\App\Http\Controllers\tenant\SalesGroupMappingController::class, 'getByIdAjax'])->name('getByIdAjax');
      Route::post('addOrUpdateAjax', [\App\Http\Controllers\tenant\SalesGroupMappingController::class, 'addOrUpdateAjax'])->name('addOrUpdateAjax');
    });

    // Setu Sales Sync — trigger a sync for any confirmed salesperson directly
    // (the actual sync POST reuses sales-visits.pipeline.sync-setu; this is
    // only the listing page).
    // Same admin/HR-only gate as Setu Salesperson Mappings above.
    Route::middleware(['role_or_permission:admin|hr|setu.manage'])->prefix('setu-sync')->name('setu-sync.')->group(function () {
      Route::get('', [SetuSyncController::class, 'index'])->name('index');
    });

    // Tally Sales Sync — supersedes Setu Sales Sync: same direct-trigger
    // listing page, sourced from Sales Group Mapping (local Tally data)
    // instead of Setu Salesperson Mappings (external API). Same gate.
    Route::middleware(['role_or_permission:admin|hr|setu.manage'])->prefix('tally-sync')->name('tally-sync.')->group(function () {
      Route::get('', [\App\Http\Controllers\tenant\TallySyncController::class, 'index'])->name('index');
    });

    // CRM Mapping Sync — data reconciliation audit's step 1 ("give
    // syncCrmMapping a button"). Lists every salesperson's unlinked Sales
    // Report rows and how many CRM companies are cached to match against;
    // the actual sync/resolve POSTs reuse sales-visits.pipeline.sync-crm-
    // mapping / resolve-crm-mapping (already built and routed).
    Route::middleware(['role_or_permission:admin|hr|crm_clients.manage'])->prefix('crm-mapping-sync')->name('crm-mapping-sync.')->group(function () {
      Route::get('', [CrmMappingSyncController::class, 'index'])->name('index');
    });

    // Tally Explorer -- browse the raw Tally data synced into HRX (purchases,
    // purchase orders, payments, the stock-item master, the full ledger master).
    // Deliberately restricted to two named accounts, not a role -- replaces the former
    // admin/HR/accounts-wide gate entirely. Trend (the Sales reconciliation view built/verified
    // across this whole Tally accuracy effort) goes to mukul@rustx.com only; everything else in
    // Tally Explorer (the read-only Purchases/Purchase Orders/Payments/Stock/Ledgers browsers, plus
    // the local-only Quick Sync trigger) goes to ai@rustx.com only.
    $tx = \App\Http\Controllers\tenant\TallyExplorerController::class;
    Route::middleware(['auth', 'tally.viewer', \App\Http\Middleware\RelaxSalesSourcesSqlMode::class, 'restrict.email:mukul@rustx.com'])->prefix('tally-explorer')->name('tally-explorer.')->group(function () use ($tx) {
      Route::get('', [$tx, 'index'])->name('index');
      Route::get('trend', [$tx, 'trend'])->name('trend');
      Route::get('trend/export', [$tx, 'trendExport'])->name('trend.export');
      Route::get('trend/values', [$tx, 'trendValues'])->name('trend.values');
      Route::get('trend/cell', [$tx, 'trendCellDetail'])->name('trend.cell');
      Route::get('trend/cell/export', [$tx, 'trendCellExport'])->name('trend.cell.export');
    });
    Route::middleware(['auth', 'tally.viewer', \App\Http\Middleware\RelaxSalesSourcesSqlMode::class, 'restrict.email:ai@rustx.com'])->prefix('tally-explorer')->name('tally-explorer.')->group(function () use ($tx) {
      Route::get('purchases', [$tx, 'purchases'])->name('purchases');
      Route::get('purchases/detail', [$tx, 'purchaseDetail'])->name('purchases.detail');
      Route::get('purchases/show', [$tx, 'purchaseShow'])->name('purchases.show');
      Route::get('items/show', [$tx, 'itemShow'])->name('items.show');
      Route::get('purchase-orders', [$tx, 'purchaseOrders'])->name('purchase-orders');
      Route::get('payments', [$tx, 'payments'])->name('payments');
      Route::get('stock', [$tx, 'stock'])->name('stock');
      Route::get('ledgers', [$tx, 'ledgers'])->name('ledgers');
      Route::get('ledgers/show', [$tx, 'ledgerShow'])->name('ledgers.show');
      Route::post('quick-sync', [$tx, 'quickSync'])->name('quick-sync');
    });

    // Sales Sources -- the manual Sales Report, Setu and the Tally daily
    // exports side by side (read-only). Company-wide sales and receivables,
    // so it is admin/HR/accounts only -- deliberately narrower than the broad
    // group this sits inside, and enforced again in the controller.
    Route::middleware(['role_or_permission:admin|hr|accounts', 'tally.viewer', \App\Http\Middleware\RelaxSalesSourcesSqlMode::class])->prefix('sales-sources')->name('sales-sources.')->group(function () {
      $c = \App\Http\Controllers\tenant\SalesSourcesController::class;
      $an = \App\Http\Controllers\tenant\SalesAnalyticsController::class;
      Route::get('', [$c, 'compare'])->name('index');
      Route::get('company', [$c, 'company'])->name('company');
      // Tally analytics dashboard (replaces the older Tally Customers / Tally Invoices pages, which now redirect here)
      Route::get('analytics', [$an, 'index'])->name('analytics');
      Route::get('analytics/data', [$an, 'data'])->name('analytics.data');
      Route::get('analytics/bill', [$an, 'bill'])->name('analytics.bill');
      Route::get('analytics/item', [$an, 'item'])->name('analytics.item');
      Route::get('analytics/customer', [$an, 'customer'])->name('analytics.customer');
      Route::get('analytics/export', [$an, 'export'])->name('analytics.export');
      Route::redirect('tally', '/sales-sources/analytics#customers')->name('tally');
      Route::redirect('tally/invoices', '/sales-sources/analytics#bills')->name('invoices');
      Route::get('tally/outstanding', [$c, 'outstanding'])->name('outstanding');
      Route::get('tally/outstanding-bills', [$c, 'outstandingBills'])->name('outstanding-bills');
      Route::get('tally/orders', [$c, 'orders'])->name('orders');
      Route::get('tally/orders/dispatch', [$c, 'orderDispatch'])->name('orders.dispatch');
      // Work Orders -- full per-order item detail, pending qty, lead-time stats, editable tracking notes.
      Route::get('work-orders', [\App\Http\Controllers\tenant\WorkOrdersController::class, 'index'])->name('work-orders');
      // Registered before {orderNo} below -- a literal path segment, would otherwise be swallowed as an order number.
      Route::get('work-orders/item-history', [\App\Http\Controllers\tenant\WorkOrdersController::class, 'itemHistory'])->name('work-orders.item-history');
      Route::get('work-orders/financial-data', [\App\Http\Controllers\tenant\WorkOrdersController::class, 'financialData'])->name('work-orders.financial-data');
      Route::get('work-orders/panel-data', [\App\Http\Controllers\tenant\WorkOrdersController::class, 'panelData'])->name('work-orders.panel-data');
      Route::get('work-orders/{orderNo}', [\App\Http\Controllers\tenant\WorkOrdersController::class, 'show'])->name('work-orders.show');
      Route::post('work-orders/{orderNo}/tracking', [\App\Http\Controllers\tenant\WorkOrdersController::class, 'updateTracking'])->name('work-orders.tracking');
      Route::get('unmapped', [$c, 'unmapped'])->name('unmapped');
      Route::get('parties', [$c, 'parties'])->name('parties');
      // Credit & Contacts' one write path into Tally -- admin-only, enforced again in the controller (the
      // group middleware above is the broader admin|hr|accounts that reads everywhere else in Sales Sources use).
      Route::post('parties/contact-preview', [\App\Http\Controllers\tenant\TallyContactUpdateController::class, 'preview'])->name('parties.contact-preview');
      Route::post('parties/contact-update', [\App\Http\Controllers\tenant\TallyContactUpdateController::class, 'update'])->name('parties.contact-update');
      Route::get('team', [$c, 'team'])->name('team');
      Route::get('finance', [$c, 'finance'])->name('finance');
      Route::get('setu', [$c, 'setu'])->name('setu');
    });

    // IP Groups
    Route::middleware(['role_or_permission:admin|hr|accounts|manager|ipgroup.manage'])->prefix('ipgroup')->name('ipgroup.')->group(function () {
      Route::get('', [IpGroupController::class, 'index'])->name('index');
      Route::get('indexAjax', [IpGroupController::class, 'indexAjax'])->name('indexAjax');
    });

    // Geofence Groups
    Route::middleware(['role_or_permission:admin|hr|accounts|manager|geofencegroup.manage'])->prefix('geofencegroup')->name('geofencegroup.')->group(function () {
      Route::get('', [GeofenceGroupController::class, 'index'])->name('index');
      Route::get('indexAjax', [GeofenceGroupController::class, 'indexAjax'])->name('indexAjax');
    });

    // QR Code Groups
    Route::middleware(['role_or_permission:admin|hr|accounts|manager|qrgroup.manage'])->prefix('qrcode')->name('qrcode.')->group(function () {
      Route::get('', [QrGroupController::class, 'index'])->name('index');
      Route::get('indexAjax', [QrGroupController::class, 'indexAjax'])->name('indexAjax');
    });

    // Units (formerly Site Attendance)
    // Units / Sites and their leave policies — admin/HR only, same reasoning as
    // Leave Policy Profiles above: the surrounding masters group is wider.
    Route::middleware(['role_or_permission:admin|hr|units.manage'])->group(function () {
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
    });

    }); // close Masters role:admin|hr|accounts|manager gate


    // --- OPERATIONS & REQUESTS ---
    Route::middleware(['role_or_permission:admin|hr|accounts|manager|attendance.view_page|attendance.manage|attendance.import|visits.manage|leave.approve|leave.approve.team|leave.approve.all|leave.view_all|outdoor_duty.approve'])->group(function () {

    // Attendance
    Route::middleware(['role_or_permission:admin|hr|accounts|manager|attendance.view_page|attendance.manage|attendance.import'])->prefix('attendance')->name('attendance.')->group(function () {
      Route::get('', [AttendanceController::class, 'index'])->name('index');
      Route::get('indexAjax', [AttendanceController::class, 'indexAjax'])->name('indexAjax');
      Route::get('registryAjax', [AttendanceController::class, 'registryAjax'])->name('registryAjax');
      Route::get('export/logs', [\App\Http\Controllers\tenant\AttendanceExportController::class, 'logs'])->name('export.logs');
      Route::get('export/registry', [\App\Http\Controllers\tenant\AttendanceExportController::class, 'registry'])->name('export.registry');
      Route::post('import', [AttendanceImportController::class, 'import'])->name('import');
      Route::get('download-sample', [AttendanceImportController::class, 'downloadSample'])->name('download-sample');
      Route::get('chart-ajax', [AttendanceController::class, 'chartAjax'])->name('chart-ajax');
      Route::get('{id}/edit', [AttendanceController::class, 'editAjax'])->name('edit');
      Route::post('{id}/update', [AttendanceController::class, 'updateAjax'])->name('update');
      Route::post('store-adjustment', [AttendanceController::class, 'storeAdjustmentAjax'])->name('store-adjustment');

      // Biometric Import Flow
      Route::get('biometric-import', [AttendanceImportController::class, 'showBiometricImport'])->name('biometric-import');
      Route::post('biometric-import/preview', [AttendanceImportController::class, 'previewBiometricImport'])->name('biometric-import.preview');
      Route::post('biometric-import/store', [AttendanceImportController::class, 'storeBiometricImport'])->name('biometric-import.store');
    });

    // Visits
    Route::middleware(['role_or_permission:admin|hr|accounts|manager|visits.manage'])->prefix('visits')->name('visits.')->group(function () {
      Route::get('', [VisitController::class, 'index'])->name('index');
      Route::get('getListAjax', [VisitController::class, 'getListAjax'])->name('getListAjax');
      Route::delete('deleteVisitAjax/{id}', [VisitController::class, 'deleteVisitAjax'])->name('deleteVisitAjax');
      Route::get('getByIdAjax/{id}', [VisitController::class, 'getByIdAjax'])->name('getByIdAjax');
    });

    // Leave Requests
    Route::middleware(['role_or_permission:admin|hr|accounts|manager|leave.approve|leave.approve.team|leave.approve.all|leave.view_all|outdoor_duty.approve'])->prefix('leaveRequests')->name('leaveRequests.')->group(function () {
      Route::get('', [LeaveController::class, 'index'])->name('index');
      Route::get('getListAjax', [LeaveController::class, 'getListAjax'])->name('getListAjax');
      Route::post('actionAjax', [LeaveController::class, 'actionAjax'])->name('actionAjax');
      Route::post('bulkActionAjax', [LeaveController::class, 'bulkActionAjax'])->name('bulkActionAjax');
      Route::get('getByIdAjax/{id}', [LeaveController::class, 'getByIdAjax'])->name('getByIdAjax');
      Route::get('summaryAjax', [LeaveController::class, 'summaryAjax'])->name('summaryAjax');
      // Outdoor Duty (inside leave page)
      Route::get('outdoorDuty/listAjax', [LeaveController::class, 'outdoorDutyListAjax'])->name('outdoorDuty.listAjax');
      Route::post('outdoorDuty/storeAjax', [LeaveController::class, 'outdoorDutyStoreAjax'])->name('outdoorDuty.storeAjax');
      Route::post('outdoorDuty/actionAjax', [LeaveController::class, 'outdoorDutyActionAjax'])->name('outdoorDuty.actionAjax');
      Route::get('outdoorDuty/getByIdAjax/{id}', [LeaveController::class, 'outdoorDutyGetByIdAjax'])->name('outdoorDuty.getByIdAjax');
    });

    }); // close Operations & Requests role:admin|hr|accounts|manager gate

    // Restricted Operations — each module gated by its own permission
    // instead of one shared OR-alternation (that used to mean granting
    // ANY one of these four permissions unlocked all three modules below).
    // Expense Requests
    // Leave Unlock Windows (Admin/HR): let people apply for leave further back
    // than the one-week limit (App\Support\LeaveBackdate) for chosen dates -
    // like the attendance unlock windows under Sales Field Ops.
    Route::middleware(['role:admin|hr'])->prefix('leave-unlocks')->name('leave-unlocks.')->group(function () {
      Route::get('', [\App\Http\Controllers\tenant\LeaveUnlockController::class, 'index'])->name('index');
      Route::post('', [\App\Http\Controllers\tenant\LeaveUnlockController::class, 'store'])->name('store');
      Route::post('{id}/toggle', [\App\Http\Controllers\tenant\LeaveUnlockController::class, 'toggle'])->name('toggle');
      Route::delete('{id}', [\App\Http\Controllers\tenant\LeaveUnlockController::class, 'destroy'])->name('destroy');
    });

    Route::middleware(['role_or_permission:admin|hr|expense.approve'])->prefix('expenseRequests')->name('expenseRequests.')->group(function () {
      Route::get('', [ExpenseController::class, 'index'])->name('index');
      Route::get('indexAjax', [ExpenseController::class, 'indexAjax'])->name('indexAjax');
      Route::get('getByIdAjax/{id}', [ExpenseController::class, 'getByIdAjax'])->name('getByIdAjax');
      Route::post('actionAjax', [ExpenseController::class, 'actionAjax'])->name('actionAjax');
    });

    // Document Management
    Route::middleware(['role_or_permission:admin|hr|document_request.manage'])->prefix('documentmanagement')->name('documentmanagement.')->group(function () {
      Route::get('', [DocumentRequestController::class, 'index'])->name('index');
      Route::get('getListAjax', [DocumentRequestController::class, 'getListAjax'])->name('getListAjax');
      Route::post('actionAjax', [DocumentRequestController::class, 'actionAjax'])->name('actionAjax');
      Route::get('getByIdAjax/{id}', [DocumentRequestController::class, 'getByIdAjax'])->name('getByIdAjax');
    });

    // Loan Requests
    Route::middleware(['role_or_permission:admin|hr|loans.view_all|loans.approve'])->prefix('loan')->name('loan.')->group(function () {
      Route::get('', [LoanRequestController::class, 'index'])->name('index');
      Route::get('getListAjax', [LoanRequestController::class, 'getListAjax'])->name('getListAjax');
      Route::post('actionAjax', [LoanRequestController::class, 'actionAjax'])->name('actionAjax');
      Route::get('getByIdAjax/{id}', [LoanRequestController::class, 'getByIdAjax'])->name('getByIdAjax');
    });

    // Task Management — original single-target endpoints (the "Add Task"
    // modal on an employee's profile page still posts to these).
    Route::middleware(['role_or_permission:admin|hr|accounts|manager|tasks.manage'])->prefix('tasks')->name('tasks.')->group(function () {
      Route::post('store', [TaskController::class, 'store'])->name('store');
      Route::post('update/{id}', [TaskController::class, 'update'])->name('update');
      Route::post('updateStatus/{id}', [TaskController::class, 'updateStatus'])->name('updateStatus');
      Route::delete('delete/{id}', [TaskController::class, 'destroy'])->name('destroy');
    });

    // Task Management portal — assign to one person, a hand-picked group, or
    // whole departments at once. Open to every signed-in user (previously
    // admin/hr only) — anyone can assign a task to anyone, but what they
    // SEE is scoped inside the controller (TaskManagementController::
    // scopeVisible()): admin/hr/tasks.manage see every task, everyone else
    // only tasks assigned to them or assigned by them.
    Route::prefix('task-management')->name('task-management.')->group(function () {
      Route::get('/', [\App\Http\Controllers\tenant\TaskManagementController::class, 'index'])->name('index');
      Route::post('/', [\App\Http\Controllers\tenant\TaskManagementController::class, 'store'])->name('store');
      Route::get('batch/{batchId}', [\App\Http\Controllers\tenant\TaskManagementController::class, 'showBatch'])->name('batch');
      Route::post('schedules/{id}/stop', [\App\Http\Controllers\tenant\TaskManagementController::class, 'stopSchedule'])->name('schedules.stop');
      Route::post('schedules/{id}/end-date', [\App\Http\Controllers\tenant\TaskManagementController::class, 'setScheduleEndDate'])->name('schedules.endDate');
      Route::post('schedules/{id}/remove-user', [\App\Http\Controllers\tenant\TaskManagementController::class, 'removeFromSchedule'])->name('schedules.removeUser');
      Route::get('schedules/{id}/history', [\App\Http\Controllers\tenant\TaskManagementController::class, 'scheduleHistory'])->name('schedules.history');
      // Static path before the {id} wildcards below, or "bulk-destroy" would
      // never be reached — it would match {id} = "bulk-destroy" on DELETE
      // instead, and PUT '{id}' would swallow this POST's path just the same
      // if the methods ever collided.
      Route::post('bulk-destroy', [\App\Http\Controllers\tenant\TaskManagementController::class, 'bulkDestroy'])->name('bulkDestroy');
      Route::put('{id}', [\App\Http\Controllers\tenant\TaskManagementController::class, 'update'])->name('update');
      Route::post('{id}/objection', [\App\Http\Controllers\tenant\TaskManagementController::class, 'objection'])->name('objection');
      Route::delete('{id}', [\App\Http\Controllers\tenant\TaskManagementController::class, 'destroy'])->name('destroy');
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
      // Employee-management screens & data are restricted to Admin / HR / Accounts
      // only. (Managers and regular employees must NOT see other employees' data.)
      // Self-service profile routes live in the separate group further below.
      // NOTE: the individual employee.* permissions below only get a user
      // PAST this route gate — CcAttendanceController-style, each controller
      // method still checks its own specific permission (see EmployeeController)
      // so e.g. employee.view_banking alone doesn't unlock employee deletion.
      Route::middleware(['role_or_permission:admin|hr|accounts|employee.view|employee.edit|employee.view_banking|employee.view_document|employee.edit_document|employee.kpa|employee.kra|employee.delete|employee.reset_password|employee.manage_leave_balance|employee.manage_payroll_adjustment|employee.manage_device|employee.lifecycle_action|sales_ops.manage'])->name('employees.')->group(function() {
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
        Route::post('moveToOnboarding/{id}', [EmployeeController::class, 'moveToOnboarding'])->name('moveToOnboarding');
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
      // Document Reject / Delete (Admin / HR, or employee.edit_document)
      Route::middleware(['role_or_permission:hr|admin|employee.edit_document'])->group(function () {
          Route::post('{userId}/reject-document', [EmployeeController::class, 'rejectDocument'])->name('rejectDocument');
          Route::post('{userId}/delete-document', [EmployeeController::class, 'deleteDocument'])->name('deleteDocument');
          Route::post('{userId}/delete-document-request', [EmployeeController::class, 'deleteDocumentRequest'])->name('deleteDocumentRequest');
      });

      });

      // Dedicated Onboarding Review Center routes — its own group (not
      // nested inside the employees. role:admin|hr|accounts block above) so
      // onboarding.manage can genuinely delegate this without that role.
      Route::middleware(['role_or_permission:admin|hr|accounts|onboarding.manage'])
          ->prefix('onboarding')->name('employees.onboarding.')->group(function() {
          Route::get('review-center', [EmployeeController::class, 'reviewCenter'])->name('review_center');
          Route::get('review/{id}', [EmployeeController::class, 'reviewOnboarding'])->name('review');
          Route::post('approve/{id}', [EmployeeController::class, 'approveOnboarding'])->name('approve');
          Route::post('resubmit/{id}', [EmployeeController::class, 'requestResubmission'])->name('resubmit');
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

    // Task/task-update attachment images — deliberately outside the
    // admin|hr|tasks.manage gate above: an assignee needs to see their own
    // task's attachment on My Tasks too. Ownership is checked inside
    // TaskController::attachment()/updateAttachment(), the same shape as
    // my-tasks/* below.
    Route::get('tasks/{id}/attachment', [TaskController::class, 'attachment'])->name('tasks.attachment');
    Route::get('task-updates/{id}/attachment', [TaskController::class, 'updateAttachment'])->name('task-updates.attachment');

    // "My Tasks" — every user's own outstanding work, whatever their role.
    Route::get('my-tasks', [\App\Http\Controllers\tenant\MyTasksController::class, 'index'])
        ->name('my-tasks.index');
    // Ownership is checked inside the controller (task.user_id === auth()->id()),
    // not by the route — this is deliberately open to any signed-in user so
    // everyone can complete their OWN tasks, whatever their role.
    Route::post('my-tasks/{task}/complete', [\App\Http\Controllers\tenant\MyTasksController::class, 'complete'])
        ->name('my-tasks.complete');
    // Same open-to-anyone shape as complete() above — ownership is checked
    // inside the controller, not the route.
    Route::post('my-tasks/{task}/resubmit', [\App\Http\Controllers\tenant\MyTasksController::class, 'resubmit'])
        ->name('my-tasks.resubmit');
    // Pending/Partially Done/Completed + an optional expected-completion
    // date — only the assignee can move their own task through this, same
    // open-to-anyone-who-owns-it shape as complete()/resubmit() above.
    Route::post('my-tasks/{task}/status', [\App\Http\Controllers\tenant\MyTasksController::class, 'updateStatus'])
        ->name('my-tasks.status');
    // A comment that doesn't change status - usable by the assignee, the
    // assigner, or anyone CC'd, checked inside the controller via
    // Task::isVisibleTo().
    Route::post('my-tasks/{task}/reply', [\App\Http\Controllers\tenant\MyTasksController::class, 'addReply'])
        ->name('my-tasks.reply');
    // The full update thread for one task, for the detail panel - same
    // isVisibleTo() gate as reply above.
    Route::get('my-tasks/{task}/thread', [\App\Http\Controllers\tenant\MyTasksController::class, 'thread'])
        ->name('my-tasks.thread');

    // "My Holidays" — every user's own applicable calendar (site/department/
    // gender scoped via Holiday::forUser()), open to any signed-in user same
    // as My Tasks above. The existing /holidays page is the admin/hr
    // management screen (gated, lists every holiday everywhere) - a
    // different job from "what applies to me".
    Route::get('my-holidays', [\App\Http\Controllers\tenant\MyHolidaysController::class, 'index'])
        ->name('my-holidays.index');

    // Attendance setup queue: accounts records each new joiner's employee ID
    // and biometric ID. Drives the login popup and the dashboard task card.
    Route::middleware(['role_or_permission:accounts|admin|hr'])
      ->prefix('employee-setup')->name('employee-setup.')->group(function () {
        Route::get('pending', [\App\Http\Controllers\tenant\EmployeeSetupTaskController::class, 'pending'])->name('pending');
        Route::get('/', [\App\Http\Controllers\tenant\EmployeeSetupTaskController::class, 'index'])->name('index');
        Route::post('{id}/complete', [\App\Http\Controllers\tenant\EmployeeSetupTaskController::class, 'complete'])->name('complete');
    });

    // Exit clearance (NOC) tracker. No role gate here on purpose: the signer
    // may be the leaver's reporting manager, who commonly holds only the
    // 'employee' role. The controller shows a user only the exits they have a
    // step in and 403s anyone with none, so access is decided by who owes a
    // clearance rather than by job title.
    Route::prefix('exit-clearance')->name('exit-clearance.')->group(function () {
        Route::get('/', [\App\Http\Controllers\tenant\ExitClearanceController::class, 'index'])->name('index');
        Route::post('{id}', [\App\Http\Controllers\tenant\ExitClearanceController::class, 'update'])->name('update');
    });

    // Employee Lifecycle (HR/Admin)
    Route::middleware(['role_or_permission:hr|admin|lifecycle.manage'])->prefix('employee-lifecycle')->name('employee-lifecycle.')->group(function () {
      Route::get('', [EmployeeLifecycleController::class, 'index'])->name('index');
      Route::get('promotions', [EmployeeLifecycleController::class, 'promotions'])->name('promotions');
      Route::post('promotions/store', [EmployeeLifecycleController::class, 'storePromotion'])->name('promotions.store');
      Route::get('transfers', [EmployeeLifecycleController::class, 'transfers'])->name('transfers');
      Route::post('transfers/store', [EmployeeLifecycleController::class, 'storeTransfer'])->name('transfers.store');
      Route::get('warnings', [EmployeeLifecycleController::class, 'warnings'])->name('warnings');
      Route::post('warnings/store', [EmployeeLifecycleController::class, 'storeWarning'])->name('warnings.store');
      Route::get('resignations', [EmployeeLifecycleController::class, 'resignations'])->name('resignations');
      Route::post('resignations/store', [EmployeeLifecycleController::class, 'storeResignation'])->name('resignations.store');
      Route::get('terminations', [EmployeeLifecycleController::class, 'terminations'])->name('terminations');
      Route::post('terminations/store', [EmployeeLifecycleController::class, 'storeTermination'])->name('terminations.store');
      Route::get('user-assets/{userId}', [EmployeeLifecycleController::class, 'getUserAssetsAjax'])->name('user-assets');
      Route::get('complaints', [EmployeeLifecycleController::class, 'complaints'])->name('complaints');
      Route::post('complaints/store', [EmployeeLifecycleController::class, 'storeComplaint'])->name('complaints.store');
      Route::get('trips', [EmployeeLifecycleController::class, 'trips'])->name('trips');
      Route::post('trips/store', [EmployeeLifecycleController::class, 'storeTrip'])->name('trips.store');
      Route::get('announcements', [EmployeeLifecycleController::class, 'announcements'])->name('announcements');
      Route::post('announcements/store', [EmployeeLifecycleController::class, 'storeAnnouncement'])->name('announcements.store');
    });

    // Employee Lifecycle (All Authenticated Users) — self-service: an
    // employee may only ever see warnings/complaints recorded about
    // themselves. Ownership is enforced inside the controller, not by role.
    Route::get('my-warnings-complaints', [EmployeeLifecycleController::class, 'myNotices'])->name('employee-lifecycle.my-notices');
    Route::get('employee-lifecycle/warnings/{warning}/attachment', [EmployeeLifecycleController::class, 'downloadWarningAttachment'])->name('employee-lifecycle.warnings.attachment');
    Route::get('employee-lifecycle/complaints/{complaint}/attachment', [EmployeeLifecycleController::class, 'downloadComplaintAttachment'])->name('employee-lifecycle.complaints.attachment');
    Route::post('employee-lifecycle/warnings/{warning}/acknowledge', [EmployeeLifecycleController::class, 'acknowledgeWarning'])->name('employee-lifecycle.warnings.acknowledge');
    Route::post('employee-lifecycle/complaints/{complaint}/acknowledge', [EmployeeLifecycleController::class, 'acknowledgeComplaint'])->name('employee-lifecycle.complaints.acknowledge');
    Route::post('employee-lifecycle/warnings/{warning}/reply', [EmployeeLifecycleController::class, 'replyToWarning'])->name('employee-lifecycle.warnings.reply');
    Route::post('employee-lifecycle/complaints/{complaint}/reply', [EmployeeLifecycleController::class, 'replyToComplaint'])->name('employee-lifecycle.complaints.reply');

    // Approvals (HR/Admin)
    Route::middleware(['role_or_permission:hr|admin|approvals.manage'])->prefix('approvals')->name('approvals.')->group(function () {
      Route::get('', [ApprovalController::class, 'index'])->name('index');
      Route::post('profile/{id}/approve', [ApprovalController::class, 'approveProfile'])->name('profile.approve');
      Route::post('profile/{id}/reject', [ApprovalController::class, 'rejectProfile'])->name('profile.reject');
      Route::post('document/{id}/approve', [ApprovalController::class, 'approveDocument'])->name('document.approve');
      Route::post('document/{id}/reject', [ApprovalController::class, 'rejectDocument'])->name('document.reject');
    });

    // Org masters are admin/HR only — managers and accounts may no longer edit
    // the company structure. The per-capability permissions stay so one person
    // can still be delegated access individually without widening either role.
    Route::middleware(['role_or_permission:admin|hr|departments.manage|designations.manage|teams.manage'])->group(function () {

    // Departments
    Route::middleware(['role_or_permission:admin|hr|departments.manage'])->prefix('departments')->name('departments.')->group(function () {
      Route::get('', [DepartmentsController::class, 'index'])->name('index');
      Route::get('indexAjax', [DepartmentsController::class, 'indexAjax'])->name('indexAjax');
      Route::post('addOrUpdateDepartmentAjax', [DepartmentsController::class, 'addOrUpdateDepartmentAjax'])->name('addOrUpdateDepartmentAjax');
      Route::get('getListAjax', [DepartmentsController::class, 'getListAjax'])->name('getListAjax');
      Route::get('getParentDepartments', [DepartmentsController::class, 'getParentDepartments'])->name('getParentDepartments');
      Route::get('getDepartmentAjax/{id}', [DepartmentsController::class, 'getDepartmentAjax'])->name('getDepartmentAjax');
      Route::get('getDepartmentUsersAjax/{id?}', [DepartmentsController::class, 'getDepartmentUsersAjax']);
      Route::delete('deleteAjax/{id}', [DepartmentsController::class, 'deleteAjax'])->name('deleteAjax');
      Route::post('changeStatus/{id}', [DepartmentsController::class, 'changeStatus'])->name('changeStatus');
    });

    // Designations
    Route::middleware(['role_or_permission:admin|hr|designations.manage'])->prefix('designations')->name('designations.')->group(function () {
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
    Route::middleware(['role_or_permission:admin|hr|teams.manage'])->prefix('teams')->name('teams.')->group(function () {
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

    }); // close Departments/Designations/Teams role:admin|hr|accounts|manager gate

    // Organization Hierarchy already moved to universal group for accessibility


    // --- ASSETS ---

    // Asset Management (Admin/HR)
    Route::middleware(['role_or_permission:hr|admin|manager|assets.view|assets.create|assets.edit|assets.delete|assets.assign'])->prefix('asset-management')->name('assets.')->group(function () {
      Route::get('', [AssetController::class, 'index'])->name('index');
      Route::get('list-ajax', [AssetController::class, 'getListAjax'])->name('listAjax');
      Route::get('export-excel', [AssetController::class, 'exportExcel'])->name('exportExcel');
      Route::get('getAssetAjax/{id}', [AssetController::class, 'getAssetAjax'])->name('getAssetAjax');
      Route::get('create', [AssetController::class, 'create'])->name('create');
      Route::get('{id}', [AssetController::class, 'show'])->name('show');
      Route::get('{id}/edit', [AssetController::class, 'edit'])->name('edit');
      Route::post('store', [AssetController::class, 'store'])->name('store');
      Route::put('{id}', [AssetController::class, 'update'])->name('update');
      Route::delete('{id}', [AssetController::class, 'destroy'])->name('destroy');
      Route::post('assign/{id}', [AssetController::class, 'assignAsset'])->name('assign');
      Route::post('unassign/{id}', [AssetController::class, 'unassignAsset'])->name('unassign');
      Route::post('transfer/{id}', [AssetController::class, 'transferAsset'])->name('transfer');
    });

    // --- RECRUITMENT MANAGEMENT ---
    // recruitment.view lets a delegate reach the module; the recruitment
    // controllers enforce their own finer-grained permission catalog
    // ("Manage Job", "Create Job", ...) for mutations. HR's module: managers
    // and accounts were let in here too, and Interview Schedule has no check of
    // its own, so any manager could open it by URL.
    Route::middleware(['role_or_permission:admin|hr|recruitment.view'])->group(function () {
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
    // Both referenced by resources/views/tenant/jobApplication/show_script.blade.php
    // but never registered -- route() threw RouteNotFoundException, 500ing the
    // whole Job Application show page for everyone (star rating + stage change
    // never even got a chance to silently fail, the page never rendered at all).
    Route::post('job-application/rating/{id}', [JobApplicationController::class, 'rating'])->name('job.application.rating');
    Route::post('job-application/stage-change', [JobApplicationController::class, 'stageChange'])->name('job.application.stage.change');

    Route::resource('custom-question', CustomQuestionController::class);

    Route::get('interview-schedule/data', [InterviewScheduleController::class, 'get_interview_schedule_data'])->name('interview-schedule.data');
    Route::resource('interview-schedule', InterviewScheduleController::class)->except(['create']);
    Route::get('interview-schedule/create/{candidate?}', [InterviewScheduleController::class, 'create'])->name('interview-schedule.create');

    }); // close Recruitment role:admin|hr gate

    }); // Close the admin|hr|manager group starting at line 108

    // --- GENERAL AUTHENTICATED ROUTES (Employee & Above) ---
    Route::middleware(['web', 'auth'])->group(function() {

    // --- BIRTHDAYS ---
    Route::get('birthdays/check', [BirthdayController::class, 'checkBirthdays'])->name('birthdays.check');
    Route::post('birthdays/send', [BirthdayController::class, 'sendWish'])->name('birthdays.send');
    Route::post('birthdays/mark-read', [BirthdayController::class, 'markWishesRead'])->name('birthdays.markRead');

        // CCARE Manual Salesperson Attendance
        Route::get('/cc-attendance', [\App\Http\Controllers\tenant\CcAttendanceController::class, 'index'])->name('cc-attendance.index');
        Route::post('/cc-attendance', [\App\Http\Controllers\tenant\CcAttendanceController::class, 'store'])->name('cc-attendance.store');
        
        // WFH approval snapshots are private: only the salesperson, their CCare
        // owner, or Admin/HR may fetch one. They are no longer served from
        // public storage, where anyone with the URL could read them.
        Route::get('/cc-attendance/proof/{id}', [\App\Http\Controllers\tenant\CcAttendanceController::class, 'proof'])->name('cc-attendance.proof');
        Route::get('/cc-attendance/report', [\App\Http\Controllers\tenant\CcAttendanceController::class, 'report'])->name('cc-attendance.report');
        Route::get('/cc-attendance/report/export', [\App\Http\Controllers\tenant\CcAttendanceController::class, 'exportReport'])->name('cc-attendance.report.export');
        Route::get('/cc-attendance/monthly', [\App\Http\Controllers\tenant\CcAttendanceController::class, 'monthly'])->name('cc-attendance.monthly');
        Route::post('/cc-attendance/monthly', [\App\Http\Controllers\tenant\CcAttendanceController::class, 'storeMonthly'])->name('cc-attendance.monthly.store');

        /* Admin/HR: open a back-dated filling window. Replaces the hardcoded
           OPEN_FROM/OPEN_TO constants, which needed a redeploy to change.
           Authorisation is enforced inside the controller by
           isAttendanceAdmin(), the same gate the register itself uses. */
        Route::get('/cc-attendance/unlocks', [\App\Http\Controllers\tenant\CcAttendanceController::class, 'unlocks'])->name('cc-attendance.unlocks');
        Route::post('/cc-attendance/unlocks', [\App\Http\Controllers\tenant\CcAttendanceController::class, 'storeUnlock'])->name('cc-attendance.unlocks.store');
        Route::post('/cc-attendance/unlocks/{id}/toggle', [\App\Http\Controllers\tenant\CcAttendanceController::class, 'toggleUnlock'])->name('cc-attendance.unlocks.toggle');
        Route::delete('/cc-attendance/unlocks/{id}', [\App\Http\Controllers\tenant\CcAttendanceController::class, 'destroyUnlock'])->name('cc-attendance.unlocks.destroy');

        // --- MIND SPEAK ---
        Route::prefix('mind-speak')->name('mind-speak.')->group(function () {
            Route::get('/', [\App\Http\Controllers\tenant\MindSpeakController::class, 'index'])->name('index');
            // Nothing stopped a script posting thousands of entries (or 20 MB
            // uploads) in a loop and burying the reviewers' dashboard.
            Route::post('/', [\App\Http\Controllers\tenant\MindSpeakController::class, 'store'])
                ->middleware('throttle:15,1')->name('store');
            Route::get('/export', [\App\Http\Controllers\tenant\MindSpeakController::class, 'export'])->name('export');
            // Attachments are private: only the submitter or a reviewer may
            // fetch one. They are no longer served from public storage.
            Route::get('/{id}/attachment', [\App\Http\Controllers\tenant\MindSpeakController::class, 'attachment'])->name('attachment');
            // Admin / HR act on a submission (approve, acknowledge or reject
            // with a reason). The controller re-checks the role.
            Route::post('/{id}/review', [\App\Http\Controllers\tenant\MindSpeakController::class, 'review'])->name('review');
        });

        // --- SALES FIELD OPERATIONS & CRM ---
        // Sales pipeline / performance pages are limited to sales staff and
        // management (see SalesAccessGuard) — not visible to all employees.
        Route::middleware('sales.guard')->prefix('sales-visits')->name('sales-visits.')->group(function () {
            Route::get('/', [\App\Http\Controllers\tenant\SalesVisitController::class, 'index'])->name('index');
            Route::get('/create', [\App\Http\Controllers\tenant\SalesVisitController::class, 'create'])->name('create');
            Route::post('/', [\App\Http\Controllers\tenant\SalesVisitController::class, 'store'])->name('store');

            // Trials — a salesperson lodges a completed trial, or CCare/NewBiz pre-book one that the
            // salesperson completes in the field. Specific paths declared before /trials/{id}.
            Route::get('/trials', [\App\Http\Controllers\tenant\SalesTrialController::class, 'index'])->name('trials.index');
            Route::get('/trials/lodge', [\App\Http\Controllers\tenant\SalesTrialController::class, 'create'])->name('trials.create');
            Route::post('/trials', [\App\Http\Controllers\tenant\SalesTrialController::class, 'store'])->name('trials.store');
            Route::get('/trials/book', [\App\Http\Controllers\tenant\SalesTrialController::class, 'bookCreate'])->name('trials.book');
            Route::post('/trials/book', [\App\Http\Controllers\tenant\SalesTrialController::class, 'bookStore'])->name('trials.book.store');
            Route::get('/trials/companies', [\App\Http\Controllers\tenant\SalesTrialController::class, 'pipelineCompanies'])->name('trials.companies');
            Route::get('/trials/{id}', [\App\Http\Controllers\tenant\SalesTrialController::class, 'show'])->whereNumber('id')->name('trials.show');
            Route::get('/trials/{id}/complete', [\App\Http\Controllers\tenant\SalesTrialController::class, 'completeForm'])->whereNumber('id')->name('trials.complete.form');
            Route::post('/trials/{id}/complete', [\App\Http\Controllers\tenant\SalesTrialController::class, 'complete'])->whereNumber('id')->name('trials.complete');
            Route::post('/trials/{id}/cancel', [\App\Http\Controllers\tenant\SalesTrialController::class, 'cancel'])->whereNumber('id')->name('trials.cancel');
            Route::post('/trials/{id}/status', [\App\Http\Controllers\tenant\SalesTrialController::class, 'addStatus'])->whereNumber('id')->name('trials.status');
            Route::post('/trials/{id}/delete', [\App\Http\Controllers\tenant\SalesTrialController::class, 'destroy'])->whereNumber('id')->name('trials.destroy');

            // Sales Report (formerly pipeline)
            Route::get('/sales-report', [\App\Http\Controllers\tenant\SalesPipelineController::class, 'index'])->name('pipeline.index');
            Route::get('/sales-pipeline', [\App\Http\Controllers\tenant\SalesPipelineController::class, 'actualPipelineIndex'])->name('actual-pipeline.index');
            Route::post('/sales-report', [\App\Http\Controllers\tenant\SalesPipelineController::class, 'store'])->name('pipeline.store');
            Route::put('/sales-report/{id}', [\App\Http\Controllers\tenant\SalesPipelineController::class, 'update'])->name('pipeline.update');
            Route::delete('/sales-report/{id}', [\App\Http\Controllers\tenant\SalesPipelineController::class, 'destroy'])->name('pipeline.destroy');
            Route::post('/sales-report/month', [\App\Http\Controllers\tenant\SalesPipelineController::class, 'monthUpdate'])->name('pipeline.month.update');
            // Tally sale per salesperson per month, entered from the Target Achievement popup.
            Route::post('/sales-report/tally-sale', [\App\Http\Controllers\tenant\SalesPipelineController::class, 'tallySaleUpdate'])->name('pipeline.tally-sale.update');

            /* Approval-gated deletion. The sales floor raises a request with a
               reason; only Admin/HR approve, and approval performs the delete.
               Covers both books — Sales Report and Sales Pipeline. */
            Route::post('/sales-report/{id}/delete-request', [\App\Http\Controllers\tenant\SalesDeleteRequestController::class, 'store'])->name('pipeline.delete-request.store');
            Route::get('/delete-requests', [\App\Http\Controllers\tenant\SalesDeleteRequestController::class, 'index'])->name('pipeline.delete-requests.index');
            Route::post('/delete-requests/{id}/approve', [\App\Http\Controllers\tenant\SalesDeleteRequestController::class, 'approve'])->name('pipeline.delete-requests.approve');
            Route::post('/delete-requests/{id}/reject', [\App\Http\Controllers\tenant\SalesDeleteRequestController::class, 'reject'])->name('pipeline.delete-requests.reject');
            // Approve or reject a whole selection in one action.
            Route::post('/delete-requests/bulk', [\App\Http\Controllers\tenant\SalesDeleteRequestController::class, 'bulk'])->name('pipeline.delete-requests.bulk');
            Route::post('/sales-report/{id}/extra-rep', [\App\Http\Controllers\tenant\SalesPipelineController::class, 'addExtraRep'])->name('pipeline.extra.add');
            Route::delete('/sales-report/{id}/extra-rep/{userId}', [\App\Http\Controllers\tenant\SalesPipelineController::class, 'removeExtraRep'])->name('pipeline.extra.remove');
            // Search any customer company-wide, then claim a role on it — GET
            // with no {id} (no row in play yet), matching /sales-report/import;
            // claim/transfer-history take {id}, matching /sales-report/{id}/extra-rep.
            Route::get('/sales-report/search-customer', [\App\Http\Controllers\tenant\SalesPipelineController::class, 'searchCustomer'])->name('pipeline.search');
            Route::post('/sales-report/{id}/claim', [\App\Http\Controllers\tenant\SalesPipelineController::class, 'claim'])->name('pipeline.claim');
            Route::get('/sales-report/{id}/transfer-history', [\App\Http\Controllers\tenant\SalesPipelineController::class, 'transferHistory'])->name('pipeline.transfer-history');
            // Target Achievement popup -> .xlsx for the month being viewed.
            Route::get('/sales-report/targets/export', [\App\Http\Controllers\tenant\SalesPipelineController::class, 'exportTargetAchievement'])->name('pipeline.targets.export');
            Route::post('/sales-report/sync-setu/{salespersonId}', [\App\Http\Controllers\tenant\SalesPipelineController::class, 'syncSetu'])->name('pipeline.sync-setu');
            Route::post('/sales-report/resolve-setu/{salespersonId}', [\App\Http\Controllers\tenant\SalesPipelineController::class, 'resolveSetu'])->name('pipeline.resolve-setu');
            Route::post('/sales-report/sync-tally/{salespersonId}', [\App\Http\Controllers\tenant\SalesPipelineController::class, 'syncTally'])->name('pipeline.sync-tally');
            Route::post('/sales-report/resolve-tally/{salespersonId}', [\App\Http\Controllers\tenant\SalesPipelineController::class, 'resolveTally'])->name('pipeline.resolve-tally');
            Route::get('/sales-report/recheck-tally/{salespersonId}', [\App\Http\Controllers\tenant\SalesPipelineController::class, 'recheckTally'])->name('pipeline.recheck-tally');

            // Hand a leaver's customers to a replacement from a chosen month (hidden: named viewers who are Admin / HR).
            Route::middleware(['handover.viewer'])->prefix('leaver-handover')->name('leaver-handover.')->group(function () {   // RestrictHandoverAccess: config('handover.viewer_emails') + Admin/HR
                Route::get('/', [\App\Http\Controllers\tenant\LeaverHandoverController::class, 'index'])->name('index');
                Route::post('/preview', [\App\Http\Controllers\tenant\LeaverHandoverController::class, 'preview'])->name('preview');
                Route::post('/apply', [\App\Http\Controllers\tenant\LeaverHandoverController::class, 'apply'])->name('apply');
                Route::get('/{id}', [\App\Http\Controllers\tenant\LeaverHandoverController::class, 'show'])->whereNumber('id')->name('show');
                Route::get('/{id}/export', [\App\Http\Controllers\tenant\LeaverHandoverController::class, 'export'])->whereNumber('id')->name('export');
                Route::post('/{id}/revert', [\App\Http\Controllers\tenant\LeaverHandoverController::class, 'revert'])->whereNumber('id')->name('revert');
            });
            Route::post('/sales-report/sync-crm-mapping/{salespersonId}', [\App\Http\Controllers\tenant\SalesPipelineController::class, 'syncCrmMapping'])->name('pipeline.sync-crm-mapping');
            Route::post('/sales-report/resolve-crm-mapping/{salespersonId}', [\App\Http\Controllers\tenant\SalesPipelineController::class, 'resolveCrmMapping'])->name('pipeline.resolve-crm-mapping');

            // Sales Pricing Desk: price list, discount finder and calculators.
            // Access is locked to one salesperson (plus admin) inside the
            // controller — not the department-wide PricingDeskAccess gate
            // the calculator-data endpoint below still uses.
            Route::prefix('pricing-desk')->name('pricing-desk.')->group(function () {
                Route::get('/', [\App\Http\Controllers\tenant\PricingDeskController::class, 'index'])->name('index');
                Route::put('/data', [\App\Http\Controllers\tenant\PricingDeskController::class, 'save'])->name('save');
                Route::get('/calculator-data', [\App\Http\Controllers\tenant\PricingDeskController::class, 'calculatorData'])->name('calculator-data');
            });

            // Customer <-> Setu verification badge/popup (customers table, not
            // per-salesperson — see CustomerSetuMappingController).
            Route::get('/sales-report/customer/{customer}/setu-preview', [\App\Http\Controllers\tenant\CustomerSetuMappingController::class, 'preview'])->name('pipeline.customer.setu-preview');
            Route::get('/sales-report/setu-customers/search', [\App\Http\Controllers\tenant\CustomerSetuMappingController::class, 'search'])->name('pipeline.setu-customers.search');
            Route::post('/sales-report/customer/{customer}/map-setu', [\App\Http\Controllers\tenant\CustomerSetuMappingController::class, 'mapSetu'])->name('pipeline.customer.map-setu');
            Route::post('/sales-report/customers/bulk-confirm-setu', [\App\Http\Controllers\tenant\CustomerSetuMappingController::class, 'bulkConfirmSuggested'])->name('pipeline.customers.bulk-confirm-setu');
            Route::get('/sales-report/customer/{customer}/sync-current-month-preview', [\App\Http\Controllers\tenant\CustomerSetuMappingController::class, 'syncCurrentMonthPreview'])->name('pipeline.customer.sync-current-month-preview');
            Route::post('/sales-report/customer/{customer}/sync-current-month', [\App\Http\Controllers\tenant\CustomerSetuMappingController::class, 'syncCurrentMonth'])->name('pipeline.customer.sync-current-month');

            // Customer <-> Tally verification badge/popup — same shape as the
            // Setu block above, but sourced from the app's own local Tally
            // tables (tally_invoices) instead of the external Setu API.
            Route::get('/sales-report/customer/{customer}/tally-preview', [\App\Http\Controllers\tenant\CustomerTallyMappingController::class, 'preview'])->name('pipeline.customer.tally-preview');
            Route::get('/sales-report/tally-customers/search', [\App\Http\Controllers\tenant\CustomerTallyMappingController::class, 'search'])->name('pipeline.tally-customers.search');
            Route::post('/sales-report/customer/{customer}/map-tally', [\App\Http\Controllers\tenant\CustomerTallyMappingController::class, 'mapTally'])->name('pipeline.customer.map-tally');
            Route::post('/sales-report/customers/bulk-confirm-tally', [\App\Http\Controllers\tenant\CustomerTallyMappingController::class, 'bulkConfirmSuggested'])->name('pipeline.customers.bulk-confirm-tally');
            Route::get('/sales-report/customer/{customer}/tally-sync-current-month-preview', [\App\Http\Controllers\tenant\CustomerTallyMappingController::class, 'syncCurrentMonthPreview'])->name('pipeline.customer.tally-sync-current-month-preview');
            Route::post('/sales-report/customer/{customer}/tally-sync-current-month', [\App\Http\Controllers\tenant\CustomerTallyMappingController::class, 'syncCurrentMonth'])->name('pipeline.customer.tally-sync-current-month');

            Route::get('/sales-report/import', [\App\Http\Controllers\tenant\PipelineImportController::class, 'showImport'])->name('pipeline.import');
            Route::post('/sales-report/import/preview', [\App\Http\Controllers\tenant\PipelineImportController::class, 'previewImport'])->name('pipeline.import.preview');
            Route::post('/sales-report/import/process', [\App\Http\Controllers\tenant\PipelineImportController::class, 'processImport'])->name('pipeline.import.process');
            Route::get('/sales-report/download-template', [\App\Http\Controllers\tenant\PipelineImportController::class, 'downloadTemplate'])->name('pipeline.download-template');

            // Redirects from old /pipeline URLs
            Route::get('/pipeline', fn() => redirect()->route('sales-visits.pipeline.index'));
            Route::get('/pipeline/import', fn() => redirect()->route('sales-visits.pipeline.import'));
            
            // Sales Targets — setting a target is Admin/HR, or a direct
            // sales_targets.manage grant. Everyone else may see targets on
            // the pages that show them, never change them.
            Route::middleware('role_or_permission:admin|hr|sales_targets.manage')->group(function () {
                Route::post('/targets/update', [\App\Http\Controllers\tenant\SalesTargetController::class, 'update'])->name('targets.update');
                Route::get('/sales-targets/import', [\App\Http\Controllers\tenant\SalesTargetImportController::class, 'showImport'])->name('sales-targets.import');
                Route::post('/sales-targets/import/preview', [\App\Http\Controllers\tenant\SalesTargetImportController::class, 'previewImport'])->name('sales-targets.import.preview');
                Route::post('/sales-targets/import/store', [\App\Http\Controllers\tenant\SalesTargetImportController::class, 'storeImport'])->name('sales-targets.import.store');
            });

            // Kingo Bingo ScoreCard
            Route::get('/kingo-bingo', [\App\Http\Controllers\tenant\KingoBingoController::class, 'index'])->name('kingo-bingo.index');
            Route::middleware('role_or_permission:admin|hr|kingo_bingo.manage')->group(function () {
                Route::get('/kingo-bingo/fill-targets', [\App\Http\Controllers\tenant\KingoBingoController::class, 'fillTargets'])->name('kingo-bingo.fill-targets');
                Route::get('/kingo-bingo/fill-targets/export/excel', [\App\Http\Controllers\tenant\KingoBingoController::class, 'exportTargetsExcel'])->name('kingo-bingo.fill-targets.excel');
                Route::get('/kingo-bingo/fill-targets/export/pdf', [\App\Http\Controllers\tenant\KingoBingoController::class, 'exportTargetsPdf'])->name('kingo-bingo.fill-targets.pdf');
                Route::put('/kingo-bingo/kpi-target', [\App\Http\Controllers\tenant\KingoBingoController::class, 'updateKpiTarget'])->name('kingo-bingo.kpi-target');
                Route::put('/kingo-bingo/target', [\App\Http\Controllers\tenant\KingoBingoController::class, 'updateTarget'])->name('kingo-bingo.target');
            });
            
            Route::get('/reports', [\App\Http\Controllers\tenant\SalesVisitController::class, 'reportSummary'])->name('reports');
            Route::get('/clients', [\App\Http\Controllers\tenant\SalesVisitController::class, 'clientIndex'])->name('clients.index');
            Route::post('/clients', [\App\Http\Controllers\tenant\SalesVisitController::class, 'clientStore'])->name('clients.store');
            Route::delete('/clients/{id}', [\App\Http\Controllers\tenant\SalesVisitController::class, 'clientDestroy'])->name('clients.destroy');
            Route::get('/{id}', [\App\Http\Controllers\tenant\SalesVisitController::class, 'show'])->name('show');
            Route::patch('/{id}/cancel', [\App\Http\Controllers\tenant\SalesVisitController::class, 'cancel'])->name('cancel');
            Route::post('/{id}/complete', [\App\Http\Controllers\tenant\SalesVisitController::class, 'complete'])->name('complete');
            Route::post('/{id}/start', [\App\Http\Controllers\tenant\SalesVisitController::class, 'start'])->name('start');
            Route::patch('/{id}/verify', [\App\Http\Controllers\tenant\SalesVisitController::class, 'verify'])->name('verify');
        });

        // Notifications - Viewable by all authenticated users
        Route::prefix('notifications')->name('tenant.notifications.')->group(function() {
            Route::get('myNotifications', [NotificationController::class, 'myNotifications'])->name('myNotifications');
            Route::get('index', [NotificationController::class, 'index'])->name('index');
            Route::post('markAsRead/{id?}', [NotificationController::class, 'markAsRead'])->name('markAsRead');
            Route::get('marksAllAsRead', [NotificationController::class, 'markAsRead'])->name('marksAllAsRead');
            Route::get('getNotificationsAjax', [NotificationController::class, 'getNotificationsAjax'])->name('getNotificationsAjax');
        });
        // Compatibility route for legacy JS calls
        Route::post('markAsRead', [NotificationController::class, 'markAsRead'])->name('tenant.notifications.markAsRead_legacy');

        // CRM Client Mappings (api-crm.rustx.net integration).
        // Read routes (index, employee tab) are scoped INSIDE the controller
        // so a scoped sales user sees only their own book. The mutating /
        // sync / raw-debug routes are admin/hr/accounts (or crm_clients.manage)
        // only — a plain salesperson must never reassign, delete, bulk-resync,
        // or dump raw CRM API data for the whole company.
        Route::middleware(['sales.guard'])->prefix('crm-clients')->name('crm-clients.')->group(function () {
            Route::get('/', [\App\Http\Controllers\tenant\CrmClientMappingController::class, 'index'])->name('index');
            Route::get('/employee/{userId}', [\App\Http\Controllers\tenant\CrmClientMappingController::class, 'employeeClients'])->name('employee');

            Route::middleware(['role_or_permission:admin|hr|accounts|crm_clients.manage'])->group(function () {
                Route::post('/', [\App\Http\Controllers\tenant\CrmClientMappingController::class, 'store'])->name('store');
                Route::post('/auto-sync', [\App\Http\Controllers\tenant\CrmClientMappingController::class, 'autoSync'])->name('auto-sync');
                Route::post('/sync-page', [\App\Http\Controllers\tenant\CrmClientMappingController::class, 'syncPage'])->name('sync-page');
                Route::get('/sync-status', [\App\Http\Controllers\tenant\CrmClientMappingController::class, 'syncStatus'])->name('sync-status');
                Route::put('/{id}', [\App\Http\Controllers\tenant\CrmClientMappingController::class, 'update'])->name('update');
                Route::delete('/{id}', [\App\Http\Controllers\tenant\CrmClientMappingController::class, 'destroy'])->name('destroy');
                Route::get('/search', [\App\Http\Controllers\tenant\CrmClientMappingController::class, 'searchCrm'])->name('search');
                Route::get('/debug-crm', [\App\Http\Controllers\tenant\CrmClientMappingController::class, 'debugCrm'])->name('debug');
            });

            // Customer 360 -- team/contacts/activity feed. Scoped INSIDE the
            // controller (canViewMapping()) exactly like index()/employeeClients()
            // above: the assigned salesperson/ccare/newbiz/billing/finance may
            // manage their own account's contacts and post activity, without
            // needing the admin-level reassign/delete permission above.
            Route::prefix('{mapping}/contacts')->group(function () {
                Route::post('/', [\App\Http\Controllers\tenant\CrmClientMappingController::class, 'storeContact'])->name('contacts.store');
            });
            Route::prefix('contacts/{id}')->group(function () {
                Route::put('/', [\App\Http\Controllers\tenant\CrmClientMappingController::class, 'updateContact'])->name('contacts.update');
                Route::delete('/', [\App\Http\Controllers\tenant\CrmClientMappingController::class, 'destroyContact'])->name('contacts.destroy');
            });
            Route::prefix('{mapping}/activities')->group(function () {
                Route::post('/', [\App\Http\Controllers\tenant\CrmClientMappingController::class, 'storeActivity'])->name('activities.store');
            });
            Route::post('activities/{id}/complete-follow-up', [\App\Http\Controllers\tenant\CrmClientMappingController::class, 'completeFollowUp'])->name('activities.complete-follow-up');

            // Internal Team (multi-person-per-role) -- same canViewMapping()
            // scoping as contacts/activities above, not the admin-only group:
            // an assigned rep manages their own account's team same as they
            // already manage its contacts.
            Route::prefix('{mapping}/team')->group(function () {
                Route::post('/', [\App\Http\Controllers\tenant\CrmClientMappingController::class, 'addTeamMember'])->name('team.store');
                Route::delete('/{userId}/{role}', [\App\Http\Controllers\tenant\CrmClientMappingController::class, 'removeTeamMember'])->name('team.destroy');
            });

            // Customer Profile -- a separate, richer tabbed view of the same
            // account (built alongside show() above, not replacing it).
            // Contacts/Team/Activities on this page reuse the routes above;
            // only the page itself, the sales-trend chart, and manual call
            // log are genuinely new here. (A Documents tab was built and
            // removed for now -- CrmClientDocument/the migration are still
            // there if it comes back.)
            Route::prefix('{mapping}/profile')->group(function () {
                Route::get('/', [\App\Http\Controllers\tenant\CustomerProfileController::class, 'show'])->name('profile');
                Route::get('/sales-trend', [\App\Http\Controllers\tenant\CustomerProfileController::class, 'salesTrend'])->name('profile.sales-trend');
                Route::get('/invoice', [\App\Http\Controllers\tenant\CustomerProfileController::class, 'invoice'])->name('profile.invoice');
                Route::post('/log-call', [\App\Http\Controllers\tenant\CustomerProfileController::class, 'logCall'])->name('profile.log-call');
            });

            // Kept last: a bare GET /{id} would otherwise shadow the specific
            // single-segment routes above (/search, /sync-status, /debug-crm).
            Route::get('/{id}', [\App\Http\Controllers\tenant\CrmClientMappingController::class, 'show'])->name('show');
        });


        // Digital Library - Viewable by all authenticated users
        Route::prefix('digital-library')->name('library.')->group(function() {
            Route::get('/', [DigitalLibraryController::class, 'index'])->name('index');
            Route::get('/access/{id}', [DigitalLibraryController::class, 'access'])->name('access');
            Route::get('/preview-frame/{id}', [DigitalLibraryController::class, 'previewFrame'])->name('previewFrame');
            
            // AI Chat & Uploads (Admin/HR, or vault.chat/vault.upload)
            Route::middleware(['role_or_permission:admin|hr|vault.chat|vault.upload'])->group(function() {
                Route::post('chat', [DigitalLibraryController::class, 'chat'])->name('chat');
                Route::post('store', [DigitalLibraryController::class, 'store'])->name('store');
                Route::post('analyze', [DigitalLibraryController::class, 'analyze'])->name('analyze');
                Route::post('bulk-store', [DigitalLibraryController::class, 'bulkStore'])->name('bulk-store');
                Route::post('reassign', [DigitalLibraryController::class, 'reassign'])->name('reassign');
                Route::post('mark-learn-completed', [DigitalLibraryController::class, 'markLearnCompleted'])->name('markLearnCompleted');
                Route::post('score-presenter', [DigitalLibraryController::class, 'scorePresenter'])->name('scorePresenter');
                Route::delete('delete-product', [DigitalLibraryController::class, 'deleteProduct'])->name('delete-product');
                
                // Taxonomy Management
                Route::post('taxonomy/add', [DigitalLibraryController::class, 'addTaxonomy'])->name('taxonomy.add');
                Route::post('taxonomy/update/{id}', [DigitalLibraryController::class, 'updateTaxonomy'])->name('taxonomy.update');
                Route::delete('taxonomy/delete/{id}', [DigitalLibraryController::class, 'deleteTaxonomy'])->name('taxonomy.delete');
            });
        });

        // Travel & Expense Claims
        Route::prefix('travel-claims')->name('travel-claims.')->group(function() {
            Route::get('/', [\App\Http\Controllers\tenant\TravelClaimController::class, 'index'])->name('index');
            Route::get('/create', [\App\Http\Controllers\tenant\TravelClaimController::class, 'create'])->name('create');
            Route::post('/store', [\App\Http\Controllers\tenant\TravelClaimController::class, 'store'])->name('store');
            // Bill files upload as soon as they are picked, before the claim is
            // saved — declared above /{id} so "attachments" is not read as an id.
            Route::post('/attachments', [\App\Http\Controllers\tenant\TravelClaimController::class, 'uploadAttachment'])->name('attachments.store');
            Route::delete('/attachments/{id}', [\App\Http\Controllers\tenant\TravelClaimController::class, 'destroyAttachment'])->name('attachments.destroy');
            Route::get('/{id}/edit', [\App\Http\Controllers\tenant\TravelClaimController::class, 'edit'])->name('edit');
            Route::post('/{id}/update', [\App\Http\Controllers\tenant\TravelClaimController::class, 'update'])->name('update');
            Route::get('/{id}', [\App\Http\Controllers\tenant\TravelClaimController::class, 'show'])->name('show');
            Route::get('/{id}/download-attachments', [\App\Http\Controllers\tenant\TravelClaimController::class, 'downloadAttachments'])->name('download-attachments');
            // One claim as a spreadsheet, so it can be verified line by line
            // away from the screen. Same gate as the attachment download.
            Route::get('/{id}/export', [\App\Http\Controllers\tenant\TravelClaimController::class, 'exportExcel'])->name('export');
            Route::delete('/{id}', [\App\Http\Controllers\tenant\TravelClaimController::class, 'destroy'])->name('destroy');
            
            // Admin/HR specific
            // The claims dashboard is where verifiers, approvers and payers all
            // work; each action keeps its own gate.
            Route::get('/admin/dashboard', [\App\Http\Controllers\tenant\TravelClaimController::class, 'adminDashboard'])->name('adminDashboard')
                ->middleware('role_or_permission:admin|hr|travel_claim.verify|travel_claim.approve|travel_claim.pay');
            Route::middleware(['role_or_permission:admin|hr|travel_claim.verify'])->group(function() {
                Route::post('/{id}/verify', [\App\Http\Controllers\tenant\TravelClaimController::class, 'verify'])->name('verify');
                Route::post('/{id}/reject', [\App\Http\Controllers\tenant\TravelClaimController::class, 'reject'])->name('reject');
                Route::post('/{id}/objection', [\App\Http\Controllers\tenant\TravelClaimController::class, 'objection'])->name('objection');
            });
            
            // Accounts specific
            Route::middleware(['role_or_permission:admin|accounts|travel_claim.approve|travel_claim.pay'])->group(function() {
                Route::post('/{id}/approve', [\App\Http\Controllers\tenant\TravelClaimController::class, 'approve'])->name('approve');
                Route::post('/{id}/pay', [\App\Http\Controllers\tenant\TravelClaimController::class, 'pay'])->name('pay');
            });
        });

        Route::middleware(['role_or_permission:admin|hr|hr_policies.manage'])->prefix('hr-policies')->name('hr-policies.')->group(function() {
            Route::get('/', [HRPolicyController::class, 'index'])->name('index');
            Route::post('/', [HRPolicyController::class, 'store'])->name('store');
            Route::post('/{id}', [HRPolicyController::class, 'update'])->name('update')->whereNumber('id');
            Route::get('/{id}/targets', [HRPolicyController::class, 'targets'])->name('targets')->whereNumber('id');
            Route::delete('/{id}', [HRPolicyController::class, 'destroy'])->name('destroy');
            Route::get('/acknowledgments/{id}', [HRPolicyController::class, 'acknowledgments'])->name('acknowledgments');
        });

        // HR Policies (All Authenticated Users)
        Route::get('my-policies', [HRPolicyController::class, 'employeeIndex'])->name('hr-policies.employee.index');
        Route::post('hr-policies/acknowledge/{id}', [HRPolicyController::class, 'acknowledge'])->name('hr-policies.acknowledge');
        Route::get('hr-policies/view/{id}', [HRPolicyController::class, 'view'])->name('hr-policies.view');
        Route::get('hr-policies/embed-url/{id}', [HRPolicyController::class, 'getEmbedUrl'])->name('hr-policies.embed-url');
        Route::get('hr-policies/stream/{id}', [HRPolicyController::class, 'stream'])->name('hr-policies.stream')->whereNumber('id');

        // AI Training — reverted to the original admin/hr + orphaned-permission
        // gate at the user's explicit request (no live access changes without
        // sign-off). The audit finding still stands: `ai.training.manage` has
        // no permission row and no UI checkbox, so this route is currently
        // reachable by the literal `admin` role only (hr is blocked by the
        // unobtainable `can:` check) — `vault.train` is available in the
        // registry as the fix, ready to apply once explicitly approved.
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

        // Uniform requests (shirt / t-shirt) — open to every authenticated user.
        // Its own prefix, not asset-management, because that group ends in a
        // catch-all Route::get('{id}') which would swallow any new path.
        Route::prefix('uniform-requests')->name('uniformRequests.')->group(function () {
          // Approver screens first, so no later {id} route can match them.
          Route::middleware(['role_or_permission:admin|hr|assets.assign'])->group(function () {
            Route::get('manage', [UniformRequestController::class, 'manage'])->name('manage');
            Route::post('{id}/decide', [UniformRequestController::class, 'decide'])->name('decide');
          });
          Route::get('', [UniformRequestController::class, 'index'])->name('index');
          Route::post('', [UniformRequestController::class, 'store'])->name('store');
          Route::post('{id}/cancel', [UniformRequestController::class, 'cancel'])->name('cancel');
        });

        // My Assets
        // getListAjax MUST come before the {id} catch-all, or the router matches
        // GET my-assets/getListAjax against {id} first (id="getListAjax") and
        // MyAssetsController::show() 404s on it -- which DataTables' error.dt
        // handler then mislabels as "Secure Session Expired" for every user,
        // every time. The asset-management group (above) and the uniform-
        // requests group's own comment already document this exact trap;
        // this group reintroduced it. Catch-all routes always go last.
        Route::prefix('my-assets')->name('myAssets.')->group(function () {
          Route::get('', [MyAssetsController::class, 'index'])->name('index');
          Route::get('getListAjax', [MyAssetsController::class, 'getListAjax'])->name('getListAjax');
          Route::get('{id}', [MyAssetsController::class, 'show'])->name('show');
          Route::post('request-maintenance/{id}', [MyAssetsController::class, 'requestMaintenance'])->name('requestMaintenance');
        });

        // Organization Hierarchy - Viewable by all authenticated users
        Route::prefix('organizationHierarchy')->name('organizationHierarchy.')->group(function () {
          Route::get('', [OrganisationHierarchyController::class, 'index'])->name('index');
        });

        // 90-Day Goals is the Sales team's own tracker: everyone in Sales works their own goals,
        // admin/HR/goals.manage see everyone's, a manager sees their team's. It has to sit HERE,
        // with the other pages every logged-in user can open (like My Scorecard): the surrounding
        // performance group only admits admin, HR and managers, which would lock out the very
        // people it is for. NinetyDayGoalController decides who may do what.
        Route::prefix('performance')->name('performance.')->group(function () {
            Route::get('/ninety-day-goals', [\App\Http\Controllers\tenant\NinetyDayGoalController::class, 'index'])->name('ninety-day-goals.index');
            Route::get('/ninety-day-goals/dashboard', [\App\Http\Controllers\tenant\NinetyDayGoalController::class, 'dashboard'])->name('ninety-day-goals.dashboard');
            Route::get('/ninety-day-goals/admin-view', [\App\Http\Controllers\tenant\NinetyDayGoalController::class, 'adminView'])->name('ninety-day-goals.admin-view');
            Route::get('/ninety-day-goals/admin-view/export', [\App\Http\Controllers\tenant\NinetyDayGoalController::class, 'adminViewExport'])->name('ninety-day-goals.admin-view.export');
            Route::get('/ninety-day-goals/program-dashboard', [\App\Http\Controllers\tenant\NinetyDayGoalController::class, 'programDashboard'])->name('ninety-day-goals.program-dashboard');
            Route::get('/ninety-day-goals/comments', [\App\Http\Controllers\tenant\NinetyDayGoalController::class, 'comments'])->name('ninety-day-goals.comments');
            Route::get('/ninety-day-goals/past', [\App\Http\Controllers\tenant\NinetyDayGoalController::class, 'past'])->name('ninety-day-goals.past');
            Route::get('/ninety-day-goals/past/{id}', [\App\Http\Controllers\tenant\NinetyDayGoalController::class, 'pastRound'])->name('ninety-day-goals.past.show');
            Route::get('/ninety-day-goals/person/{id}', [\App\Http\Controllers\tenant\NinetyDayGoalController::class, 'person'])->name('ninety-day-goals.person');
            Route::get('/ninety-day-goals/my-program', [\App\Http\Controllers\tenant\NinetyDayGoalController::class, 'myProgram'])->name('ninety-day-goals.my-program');
            Route::get('/ninety-day-goals/my-dashboard', [\App\Http\Controllers\tenant\NinetyDayGoalController::class, 'myDashboard'])->name('ninety-day-goals.my-dashboard');
            Route::get('/ninety-day-goals/round/{id}', [\App\Http\Controllers\tenant\NinetyDayGoalController::class, 'roundDetail'])->name('ninety-day-goals.round');
            Route::get('/ninety-day-goals/program/{id}', [\App\Http\Controllers\tenant\NinetyDayGoalController::class, 'programDetail'])->name('ninety-day-goals.program');
            Route::post('/ninety-day-goals/program/{id}/remind', [\App\Http\Controllers\tenant\NinetyDayGoalController::class, 'programSendReminder'])->name('ninety-day-goals.program.remind');
            Route::put('/ninety-day-goals/program/{id}', [\App\Http\Controllers\tenant\NinetyDayGoalController::class, 'programUpdate'])->name('ninety-day-goals.program.update');
            Route::delete('/ninety-day-goals/program/{id}', [\App\Http\Controllers\tenant\NinetyDayGoalController::class, 'programDestroy'])->name('ninety-day-goals.program.destroy');
            Route::post('/ninety-day-goals/program/{id}/participants', [\App\Http\Controllers\tenant\NinetyDayGoalController::class, 'programAddParticipants'])->name('ninety-day-goals.program.participants.add');
            Route::delete('/ninety-day-goals/program/{id}/participants/{userId}', [\App\Http\Controllers\tenant\NinetyDayGoalController::class, 'programRemoveParticipant'])->name('ninety-day-goals.program.participants.remove');
            Route::post('/ninety-day-goals/program/{id}/window', [\App\Http\Controllers\tenant\NinetyDayGoalController::class, 'programOpenWindow'])->name('ninety-day-goals.program.window');
            Route::get('/ninety-day-goals/programs', [\App\Http\Controllers\tenant\NinetyDayGoalController::class, 'programs'])->name('ninety-day-goals.programs.index');
            Route::get('/ninety-day-goals/programs/create', [\App\Http\Controllers\tenant\NinetyDayGoalController::class, 'programCreate'])->name('ninety-day-goals.programs.create');
            Route::post('/ninety-day-goals/programs', [\App\Http\Controllers\tenant\NinetyDayGoalController::class, 'programStore'])->name('ninety-day-goals.programs.store');
            Route::get('/ninety-day-goals/pipeline-customers', [\App\Http\Controllers\tenant\NinetyDayGoalController::class, 'pipelineCustomers'])->name('ninety-day-goals.pipeline-customers');
            Route::post('/ninety-day-goals', [\App\Http\Controllers\tenant\NinetyDayGoalController::class, 'store'])->name('ninety-day-goals.store');
            Route::post('/ninety-day-goals/checkin', [\App\Http\Controllers\tenant\NinetyDayGoalController::class, 'checkin'])->name('ninety-day-goals.checkin');
            Route::put('/ninety-day-goals/{id}', [\App\Http\Controllers\tenant\NinetyDayGoalController::class, 'update'])->name('ninety-day-goals.update');
            Route::delete('/ninety-day-goals/{id}', [\App\Http\Controllers\tenant\NinetyDayGoalController::class, 'destroy'])->name('ninety-day-goals.destroy');
            Route::post('/ninety-day-goals/{id}/delete-request', [\App\Http\Controllers\tenant\NinetyDayGoalController::class, 'requestDelete'])->name('ninety-day-goals.delete-request');
            Route::put('/ninety-day-goals/delete-request/{id}', [\App\Http\Controllers\tenant\NinetyDayGoalController::class, 'decideDelete'])->name('ninety-day-goals.delete-request.decide');
            Route::get('/ninety-day-goals/delete-requests', [\App\Http\Controllers\tenant\NinetyDayGoalController::class, 'deleteRequestsIndex'])->name('ninety-day-goals.delete-requests.index');
            Route::post('/ninety-day-goals/notes', [\App\Http\Controllers\tenant\NinetyDayGoalController::class, 'note'])->name('ninety-day-goals.note');
            Route::post('/ninety-day-goals/confirm-upsell', [\App\Http\Controllers\tenant\NinetyDayGoalController::class, 'confirmUpsell'])->name('ninety-day-goals.confirm-upsell');
            Route::delete('/ninety-day-goals/notes/{id}', [\App\Http\Controllers\tenant\NinetyDayGoalController::class, 'destroyNote'])->name('ninety-day-goals.note.destroy');
        });

        // Work Control (Viewable by all authenticated users)
        Route::prefix('work-control')->name('tenant.work-control.')->group(function () {
            Route::get('/', [\App\Http\Controllers\tenant\WorkControlController::class, 'index'])->name('index');
            Route::get('/export', [\App\Http\Controllers\tenant\WorkControlController::class, 'export'])->name('export');
            Route::get('/my-routines', [\App\Http\Controllers\tenant\WorkControlController::class, 'myRoutines'])->name('my-routines');
            Route::post('/store', [\App\Http\Controllers\tenant\WorkControlController::class, 'store'])->name('store');
        });

        // KPI / KRA (DWM-driven).
        //
        // "My Scorecard" is a SELF-service page — KpiController::my() and
        // ::complete() both scope strictly to the caller's own user_id — but
        // it was locked behind admin|kpi.manage while every employee's
        // dashboard links to it, so each of them got a 403 on their own
        // scorecard. Opened to any authenticated user; management stays
        // admin-only below.
        Route::prefix('kpi')->name('tenant.kpi.')->group(function () {
            Route::get('/my', [\App\Http\Controllers\tenant\KpiController::class, 'my'])->name('my');
            Route::post('/{id}/complete', [\App\Http\Controllers\tenant\KpiController::class, 'complete'])->name('complete');
            // Weekly self-reporting for manually measured KPIs.
            Route::post('/{id}/entry', [\App\Http\Controllers\tenant\KpiController::class, 'submitEntry'])->name('entry');

            // Review queue for weekly submissions. Deliberately outside the
            // admin|hr group below: a reporting manager reviews their own team,
            // and KpiController re-checks the role on every one of these.
            Route::get('/approvals', [\App\Http\Controllers\tenant\KpiController::class, 'approvals'])->name('approvals');
            Route::get('/approvals/export', [\App\Http\Controllers\tenant\KpiController::class, 'exportApprovals'])->name('approvals.export');
            Route::post('/entries/{entryId}/review', [\App\Http\Controllers\tenant\KpiController::class, 'reviewEntry'])->name('entries.review');

            // Bulk manual entry. Deliberately OUTSIDE the admin|hr group below:
            // a KPI data owner is whoever actually holds the number -- the person
            // running the GSM gateway, the one who keeps the CRM -- and they are
            // usually neither admin nor HR. KpiBulkEntryController checks on every
            // action that the caller owns the KPI being filled, and that each row
            // belongs to a team they own it for.
            Route::get('/bulk-entry', [\App\Http\Controllers\tenant\KpiBulkEntryController::class, 'index'])->name('bulk-entry');
            Route::post('/bulk-entry', [\App\Http\Controllers\tenant\KpiBulkEntryController::class, 'store'])->name('bulk-entry.store');
            Route::get('/bulk-entry/template', [\App\Http\Controllers\tenant\KpiBulkEntryController::class, 'template'])->name('bulk-entry.template');
            Route::post('/bulk-entry/import', [\App\Http\Controllers\tenant\KpiBulkEntryController::class, 'import'])->name('bulk-entry.import');
            Route::get('/bulk-entry/preview', [\App\Http\Controllers\tenant\KpiBulkEntryController::class, 'preview'])->name('bulk-entry.preview');
            Route::post('/bulk-entry/import/apply', [\App\Http\Controllers\tenant\KpiBulkEntryController::class, 'importApply'])->name('bulk-entry.import.apply');
            Route::post('/bulk-entry/owner', [\App\Http\Controllers\tenant\KpiBulkEntryController::class, 'addOwner'])->name('bulk-entry.owner.add');
            Route::delete('/bulk-entry/owner/{id}', [\App\Http\Controllers\tenant\KpiBulkEntryController::class, 'removeOwner'])->name('bulk-entry.owner.remove');
        });

        // Management side. NOTE: kept admin-only by default (not widened to
        // hr|manager, even though KpiController itself allows them) to avoid a
        // live access change without explicit sign-off — kpi.manage is
        // available for individual delegation.
        // HR added alongside admin: the sidebar has always offered these to
        // both, but 'kpi.manage' was never created as a permission, so the
        // role_or_permission check resolved to admin-only and HR got a 403 on
        // a link they could see. The permission stays listed for future
        // per-person delegation.
        Route::middleware(['role_or_permission:admin|hr|kpi.manage'])->prefix('kpi')->name('tenant.kpi.')->group(function () {
            Route::get('/manage', [\App\Http\Controllers\tenant\KpiController::class, 'manage'])->name('manage');
            Route::post('/store', [\App\Http\Controllers\tenant\KpiController::class, 'store'])->name('store');
            Route::put('/{id}', [\App\Http\Controllers\tenant\KpiController::class, 'update'])->name('update');
            Route::delete('/{id}', [\App\Http\Controllers\tenant\KpiController::class, 'destroy'])->name('destroy');
            Route::post('/{id}/evaluate', [\App\Http\Controllers\tenant\KpiController::class, 'evaluate'])->name('evaluate');
            Route::post('/assign-from-routine', [\App\Http\Controllers\tenant\KpiController::class, 'assignFromRoutine'])->name('assignFromRoutine');

            // Department-level KPI/KRA catalogue. No clash with the /{id}
            // routes above: those match a single segment, these are two.
            Route::get('/templates', [\App\Http\Controllers\tenant\KpiController::class, 'templates'])->name('templates');
            Route::post('/templates', [\App\Http\Controllers\tenant\KpiController::class, 'storeTemplate'])->name('templates.store');
            Route::put('/templates/{id}', [\App\Http\Controllers\tenant\KpiController::class, 'updateTemplate'])->name('templates.update');
            Route::delete('/templates/{id}', [\App\Http\Controllers\tenant\KpiController::class, 'destroyTemplate'])->name('templates.destroy');
            Route::post('/templates/{id}/apply', [\App\Http\Controllers\tenant\KpiController::class, 'applyTemplate'])->name('templates.apply');
        });
    });

    // --- RE-OPEN ADMINISTRATIVE ROUTES (Admin/HR/Manager) ---
    Route::middleware([
      'web',
      'auth',
      // Every page in this group has its own gate below; this outer list only
      // has to let each of their boxes in.
      'role_or_permission:admin|hr|manager|device.manage|biometric.manage|account.manage|assets.manage_categories|sos.manage|chat.access|goals.manage|scorecards.manage'
    ])->group(function () {


    // Asset Categories (Admin/HR)
    Route::middleware(['role_or_permission:admin|hr|manager|assets.manage_categories'])->prefix('asset-categories')->name('assetCategories.')->group(function () {
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


    // --- SYSTEM UTILITIES ---

    // Audit Logs — admin/HR only (the log shows every user's changes)
    Route::prefix('auditLogs')->name('auditLogs.')->middleware('role_or_permission:admin|hr|audit.view')->group(function () {
      Route::get('', [AuditLogController::class, 'index'])->name('index');
      Route::get('show/{id}', [AuditLogController::class, 'show'])->name('show');
    });

    // Device Status & Biometric Devices
    Route::middleware(['role_or_permission:admin|hr|manager|device.manage|account.manage'])->prefix('device')->name('device.')->group(function () {
      Route::get('', [DeviceController::class, 'index'])->name('index');
      Route::get('indexAjax', [DeviceController::class, 'indexAjax'])->name('indexAjax');
      Route::get('getByIdAjax/{id}', [DeviceController::class, 'getByIdAjax'])->name('getByIdAjax');
      Route::delete('deleteAjax/{id}', [DeviceController::class, 'deleteAjax'])->name('deleteAjax');
    });

    Route::middleware(['role_or_permission:admin|hr|manager|biometric.manage'])->prefix('biometric')->name('biometric.')->group(function () {
      Route::get('', [BiometricDeviceController::class, 'index'])->name('index');
      Route::get('create', [BiometricDeviceController::class, 'create'])->name('create');
      Route::post('store', [BiometricDeviceController::class, 'store'])->name('store');
      Route::get('{biometric}/edit', [BiometricDeviceController::class, 'edit'])->name('edit');
      Route::put('{biometric}', [BiometricDeviceController::class, 'update'])->name('update');
      Route::delete('{biometric}', [BiometricDeviceController::class, 'destroy'])->name('destroy');
      Route::post('test-connection', [BiometricDeviceController::class, 'testConnection'])->name('test-connection');
    });

    // SOS (Addon Check)
    Route::middleware([SOSAddonCheck::class, 'role_or_permission:admin|hr|sos.manage'])->group(function () {
      Route::get('/sos', [SOSController::class, 'index'])->name('sos.index');
      Route::get('/sos/fetch', [SOSController::class, 'fetchSOSRequests'])->name('sos.fetch');
      Route::post('/sos/resolve/{id}', [SOSController::class, 'markAsResolved'])->name('sos.resolve');
    });

    // Admin Chat
    Route::middleware(['role_or_permission:admin|hr|chat.access'])->get('/admin/chat', [\App\Http\Controllers\tenant\ChatController::class, 'index'])->name('chat.index');

    // Account Management
    Route::prefix('account')->name('account.')->group(function () {
      Route::get('', [AccountController::class, 'index'])->name('index');
      Route::get('activeInactiveUserAjax/{id}', [AccountController::class, 'activeInactiveUserAjax'])->name('activeInactiveUserAjax');
      Route::get('suspendUserAjax/{id}', [AccountController::class, 'suspendUserAjax'])->name('suspendUserAjax');
      Route::get('viewUser/{id}', [AccountController::class, 'viewUser'])->name('viewUser');
      Route::get('indexAjax', [AccountController::class, 'userListAjax'])->name('userListAjax');
      Route::get('getRolesAjax', [AccountController::class, 'getRolesAjax'])->name('getRolesAjax');
      Route::get('getUsersAjax', [AccountController::class, 'getUsersAjax'])->name('getUsersAjax');
      Route::get('getUsersByRoleAjax/{role}', [AccountController::class, 'getUsersByRoleAjax'])->name('getUsersByRoleAjax');
      Route::get('editUserAjax/{id}', [AccountController::class, 'editUserAjax'])->name('editUserAjax');
      Route::post('changeUserStatusAjax/{id}', [AccountController::class, 'changeUserStatusAjax'])->name('changeUserStatusAjax');
      Route::post('changePassword', [AccountController::class, 'changePassword'])->name('changePassword');

      // Account deletion and status changes -- admin only.
      Route::middleware('role_or_permission:admin')->group(function () {
        Route::get('deleteUserAjax/{id}', [AccountController::class, 'deleteUserAjax'])->name('deleteUserAjaxTenant');
        Route::post('updateUserStatusAjax/{id}', [AccountController::class, 'updateUserStatusAjax'])->name('updateUserStatusAjax');
      });

      // Assigning a role to a user -- admin or HR. HR may only hand out
      // non-admin-tier roles (enforced in the controller, which also hides
      // admin/super_admin from the role list HR is shown at all).
      Route::middleware('role_or_permission:admin|hr')->group(function () {
        Route::post('addOrUpdateUserAjax', [AccountController::class, 'addOrUpdateUserAjax'])->name('addOrUpdateUserAjax');
        Route::post('updateUserAjax/{id}', [AccountController::class, 'updateUserAjax'])->name('updateUserAjax');
      });
    });




    Route::prefix('performance')->name('performance.')->group(function () {
        // Kept admin-only by default (not widened to hr|manager, even though
        // the controllers themselves allow them) at the user's explicit
        // request — no live access change without sign-off. goals.manage /
        // scorecards.manage are available for individual delegation without
        // widening the role-based default.
        Route::middleware(['role_or_permission:admin|goals.manage|scorecards.manage'])->group(function () {
            Route::get('/scorecards', [\App\Http\Controllers\tenant\SalesAssessmentController::class, 'index'])->name('scorecards.index');
            Route::post('/scorecards/generate', [\App\Http\Controllers\tenant\SalesAssessmentController::class, 'generate'])->name('scorecards.generate');
            Route::get('/scorecards/{id}', [\App\Http\Controllers\tenant\SalesAssessmentController::class, 'show'])->name('scorecards.show');
            Route::post('/scorecards/{id}', [\App\Http\Controllers\tenant\SalesAssessmentController::class, 'updateReview'])->name('scorecards.update-review');
        });
    });
});

// --- ROLES & PERMISSIONS ---
// Deliberately its own top-level group (not nested inside the
// role:admin|hr|manager block above) so `roles.manage` can genuinely
// delegate this to someone without that role — nesting it inside a
// role:-gated group would mean the outer AND-check blocks a
// roles.manage-only holder before this middleware ever runs.
Route::middleware(['web', 'auth', 'role_or_permission:admin|hr|manager|roles.manage'])->group(function () {
    Route::get('roles', [RoleController::class, 'index'])->name('roles.index');
    Route::get('roles/{id}/panelAjax', [RoleController::class, 'panelAjax'])->name('roles.panelAjax');
    Route::get('roles/{id}/getRolePermissionsAjax', [RoleController::class, 'getRolePermissionsAjax'])->name('roles.getRolePermissionsAjax');
    Route::post('roles/addOrUpdateAjax', [RoleController::class, 'addOrUpdateAjax'])->name('roles.addOrUpdateAjax');
    Route::delete('roles/deleteAjax/{id}', [RoleController::class, 'deleteAjax'])->name('roles.deleteAjax');

    // User-specific special permissions
    Route::get('roles/special-permissions', [RoleController::class, 'specialPermissionsPage'])->name('roles.specialPermissions');
    Route::get('roles/specialPermissionsDataAjax/{userId}', [RoleController::class, 'specialPermissionsDataAjax'])->name('roles.specialPermissionsDataAjax');
    Route::post('roles/toggleUserPermissionAjax', [RoleController::class, 'toggleUserPermissionAjax'])->name('roles.toggleUserPermissionAjax');
    Route::post('roles/previewAccessAjax', [RoleController::class, 'previewAccessAjax'])->name('roles.previewAccessAjax');
});
