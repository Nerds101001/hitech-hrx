<?php

use App\Http\Controllers\AccountController;
use App\Http\Controllers\AddonController;
use App\Http\Controllers\AuditLogController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\BaseController;
use App\Http\Controllers\CouponController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\DemoController;
use App\Http\Controllers\DomainRequestController;
use App\Http\Controllers\language\LanguageController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\OfflineRequestController;
use App\Http\Controllers\OrderController;
use App\Http\Controllers\Payment\OfflinePaymentController;
use App\Http\Controllers\Payment\PaypalPaymentController;
use App\Http\Controllers\Payment\RazorpayPaymentController;
use App\Http\Controllers\PlanController;
use App\Http\Controllers\RoleController;
use App\Http\Controllers\SaSettingsController;
use App\Http\Controllers\SupportController;
use App\Http\Controllers\tenantController;
use App\Http\Controllers\UtilitiesController;
use Illuminate\Support\Facades\Route;


require __DIR__ . '/auth.php';
require __DIR__ . '/customer.php';
require __DIR__ . '/tenant.php';

// Redesign Preview Routes
// Route::get('/preview-reset', [AccountController::class, 'previewReset']); // Commented out if not used
Route::get('/reset-password', [AccountController::class, 'resetPasswordRedirect'])->name('password.reset.redirect');



Route::get('/demo', [DemoController::class, 'index'])->name('demo.show');

Route::redirect('/login', '/auth/login')->name('login');


Route::middleware('auth:web')->group(function () {

  Route::get('support', [SupportController::class, 'index'])->name('support.index');

  Route::post('razorpay/razorPayPayment', [RazorpayPaymentController::class, 'razorPayPayment'])->name('razorpay.razorPayPayment');
  Route::post('razorpay/razorPayPaymentForAddUser', [RazorpayPaymentController::class, 'razorPayPaymentForAddUser'])->name('razorpay.razorPayPaymentForAddUser');
  Route::post('razorpay/razorPayPaymentForRenewal', [RazorpayPaymentController::class, 'razorPayPaymentForRenewal'])->name('razorpay.razorPayPaymentForRenewal');
  Route::post('razorpay/razorPayPaymentForUpgrade', [RazorpayPaymentController::class, 'razorPayPaymentForUpgrade'])->name('razorpay.razorPayPaymentForUpgrade');
  Route::get('razorpay/transaction/callback/{transaction_id}/{local_order_id}', [RazorpayPaymentController::class, 'razorpayCallback'])->name('razorpay.transaction.callback');

  Route::prefix('paypal/')->name('paypal.')->group(function () {
    Route::post('paypalPayment', [PaypalPaymentController::class, 'paypalPayment'])->name('paypalPayment');
    Route::post('paypalPaymentForAddUser', [PaypalPaymentController::class, 'paypalPaymentForAddUser'])->name('paypalPaymentForAddUser');
    Route::post('paypalPaymentForRenewal', [PaypalPaymentController::class, 'paypalPaymentForRenewal'])->name('paypalPaymentForRenewal');
    Route::post('paypalPaymentForUpgrade', [PaypalPaymentController::class, 'paypalPaymentForUpgrade'])->name('paypalPaymentForUpgrade');
    Route::get('success', [PaypalPaymentController::class, 'success'])->name('success');
    Route::get('cancel', [PaypalPaymentController::class, 'cancel'])->name('cancel');
  });

  Route::prefix('offlinePayment/')->name('offlinePayment.')->group(function () {
    Route::post('create', [OfflinePaymentController::class, 'create'])->name('create');
    Route::post('payOfflineForUserAdd', [OfflinePaymentController::class, 'payOfflineForUserAdd'])->name('payOfflineForUserAdd');
    Route::post('payOfflineForRenewal', [OfflinePaymentController::class, 'payOfflineForRenewal'])->name('payOfflineForRenewal');
    Route::post('payOfflineForUpgrade', [OfflinePaymentController::class, 'payOfflineForUpgrade'])->name('payOfflineForUpgrade');
    Route::get('cancelOfflineRequest/{id}', [OfflinePaymentController::class, 'cancelOfflineRequest'])->name('cancelOfflineRequest');
  });


  Route::get('tenant', [TenantController::class, 'index'])->name('tenant.index');
  Route::post('tenant/store', [TenantController::class, 'store'])->name('tenant.store');

  Route::prefix('domainRequests/')->name('domainRequests.')->group(function () {
    Route::get('', [DomainRequestController::class, 'index'])->name('index');
    Route::get('indexAjax', [DomainRequestController::class, 'indexAjax'])->name('indexAjax');
    Route::get('getByIdAjax/{id}', [DomainRequestController::class, 'getByIdAjax'])->name('getByIdAjax');
    Route::post('actionAjax', [DomainRequestController::class, 'actionAjax'])->name('actionAjax');
  });


  Route::prefix('offlineRequests/')->name('offlineRequests.')->group(function () {
    Route::get('', [OfflineRequestController::class, 'index'])->name('index');
    Route::get('indexAjax', [OfflineRequestController::class, 'indexAjax'])->name('indexAjax');
    Route::get('getByIdAjax/{id}', [OfflineRequestController::class, 'getByIdAjax'])->name('getByIdAjax');
    Route::post('actionAjax', [OfflineRequestController::class, 'actionAjax'])->name('actionAjax');
  });

  Route::prefix('orders/')->name('orders.')->group(function () {
    Route::get('', [OrderController::class, 'index'])->name('index');
    Route::get('indexAjax', [OrderController::class, 'indexAjax'])->name('indexAjax');
  });

  Route::prefix('plans/')->name('plans.')->group(function () {
    Route::get('', [PlanController::class, 'index'])->name('index');
    Route::get('indexAjax', [PlanController::class, 'indexAjax'])->name('indexAjax');
    Route::post('addOrUpdatePlanAjax', [PlanController::class, 'addOrUpdatePlanAjax'])->name('addOrUpdatePlanAjax');
    Route::get('getPlanAjax/{id}', [PlanController::class, 'getPlanAjax'])->name('getPlanAjax');
    Route::post('changeStatusAjax/{id}', [PlanController::class, 'changeStatusAjax'])->name('changeStatusAjax');
    Route::get('create', [PlanController::class, 'create'])->name('create');
    Route::post('store', [PlanController::class, 'store'])->name('store');
    Route::get('edit/{id}', [PlanController::class, 'edit'])->name('edit');
    Route::put('update/{id}', [PlanController::class, 'update'])->name('update');
  });

  Route::prefix('coupons/')->name('coupons.')->group(function () {
    Route::get('', [CouponController::class, 'index'])->name('index');
    Route::get('indexAjax', [CouponController::class, 'indexAjax'])->name('indexAjax');
    Route::post('createAjax', [CouponController::class, 'createAjax'])->name('createAjax');
    Route::get('generateUniqueCodeAjax', [CouponController::class, 'generateUniqueCodeAjax'])->name('generateUniqueCodeAjax');
    Route::post('changeStatusAjax/{id}', [CouponController::class, 'changeStatusAjax'])->name('changeStatusAjax');
    Route::delete('deleteAjax/{id}', [CouponController::class, 'deleteAjax'])->name('deleteAjax');
  });


  //Search Routes
  Route::get('/getSearchDataAjax', [BaseController::class, 'getSearchDataAjax'])->name('search.Ajax');

  //Addon Routes
  if (config('custom.custom.displayAddon')) {
    Route::get('/addons', [AddonController::class, 'index'])->name('addons.index');
    Route::post('/addons/activate', [AddonController::class, 'activate'])->name('module.activate');
    Route::post('/addons/deactivate', [AddonController::class, 'deactivate'])->name('module.deactivate');
    Route::post('/addons/upload', [AddonController::class, 'upload'])->name('module.upload');
    Route::post('/addons/update', [AddonController::class, 'update'])->name('module.update');
    Route::delete('/addons/uninstall', [AddonController::class, 'uninstall'])->name('module.uninstall');
  }

  Route::get('/lang/{locale}', [LanguageController::class, 'swap']);

  Route::middleware('auth')->group(function () {

    Route::post('/auth/logout', [AuthController::class, 'logout'])->name('auth.logout');

    Route::get('roles', [RoleController::class, 'index'])->name('roles.index');
    Route::delete('roles/deleteAjax/{id}', [RoleController::class, 'deleteAjax'])->name('roles.deleteAjax');

    Route::get('/dashboard', [DashboardController::class, 'index'])->name('superAdmin.dashboard');
    Route::get('account', [AccountController::class, 'index'])->name('account.index');
    Route::get('account/activeInactiveUserAjax/{id}', [AccountController::class, 'activeInactiveUserAjax'])->name('account.activeInactiveUserAjax');
    Route::get('account/suspendUserAjax/{id}', [AccountController::class, 'suspendUserAjax'])->name('account.suspendUserAjax');
    // Route::get('account/deleteUserAjax/{id}', [AccountController::class, 'deleteUserAjax'])->name('account.deleteUserAjax');
    Route::get('account/viewUser/{id}', [AccountController::class, 'viewUser'])->name('account.viewUser');
    Route::get('account/myProfile', [AccountController::class, 'myProfile'])->name('account.myProfile');
    Route::get('account/indexAjax', [AccountController::class, 'userListAjax'])->name('account.userListAjax');
    Route::delete('account/deleteUserAjax/{id}', [AccountController::class, 'deleteUserAjax'])->name('account.deleteUserAjax');
    Route::get('account/getRolesAjax', [AccountController::class, 'getRolesAjax'])->name('account.getRolesAjax');
    Route::get('account/getUsersAjax', [AccountController::class, 'getUsersAjax'])->name('account.getUsersAjax');
    Route::get('account/getUsersByRoleAjax/{role}', [AccountController::class, 'getUsersByRoleAjax'])->name('account.getUsersByRoleAjax');
    Route::post('account/addOrUpdateUserAjax', [AccountController::class, 'addOrUpdateUserAjax'])->name('account.addOrUpdateUserAjax');
    Route::get('account/editUserAjax/{id}', [AccountController::class, 'editUserAjax'])->name('account.editUserAjax');
    Route::post('account/updateUserAjax/{id}', [AccountController::class, 'updateUserAjax'])->name('account.updateUserAjax');
    // Route::post('account/updateUserStatusAjax/{id}', [AccountController::class, 'updateUserStatusAjax'])->name('account.updateUserStatusAjax');
    Route::post('account/changeUserStatusAjax/{id}', [AccountController::class, 'changeUserStatusAjax'])->name('account.changeUserStatusAjax');
    Route::post('account/changePassword', [AccountController::class, 'changePassword'])->name('account.changePassword');
    //Account Customer
    Route::get('account/customerIndex', [AccountController::class, 'customerIndex'])->name('account.customerIndex');
    Route::get('account/customerIndexAjax', [AccountController::class, 'customerIndexAjax'])->name('account.customerIndexAjax');

    Route::get('notifications', [NotificationController::class, 'index'])->name('notifications.index');
    Route::get('notifications/myNotifications', [NotificationController::class, 'myNotifications'])->name('notifications.myNotifications');
    Route::get('notifications/marksAllAsRead', [NotificationController::class, 'markAsRead'])->name('notifications.marksAllAsRead');
    Route::post('notifications/markAsRead/{id}', [NotificationController::class, 'markAsRead'])->name('notifications.markAsRead');


    //Audit Logs
    Route::get('auditLogs', [AuditLogController::class, 'index'])->name('auditLogs.index');
    Route::get('auditLogs/show/{id}', [AuditLogController::class, 'show'])->name('auditLogs.show');

    //utilities Route
    Route::get('utilities', [UtilitiesController::class, 'index'])->name('utilities.index');
    Route::post('utilities/createBackup', [UtilitiesController::class, 'createBackup'])->name('utilities.createBackup');
    Route::get('utilities/downloadBackup/{fileName}', [UtilitiesController::class, 'downloadBackup'])->name('utilities.downloadBackup');
    Route::get('utilities/getBackupList', [UtilitiesController::class, 'getBackupListAjax'])->name('utilities.getBackupList');
    Route::delete('utilities/deleteBackup/{file}', [UtilitiesController::class, 'deleteBackup'])->name('utilities.deleteBackup');
    Route::post('utilities/restoreBackup/{fileName}', [UtilitiesController::class, 'restoreBackup'])->name('utilities.restoreBackup');
    Route::post('utilities/clearCache', [UtilitiesController::class, 'clearCache'])->name('utilities.clearCache');
    Route::post('utilities/clearLog', [UtilitiesController::class, 'clearLog'])->name('utilities.clearLog');
  });
});
