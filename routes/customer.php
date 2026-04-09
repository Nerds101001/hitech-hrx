<?php

use App\Http\Controllers\CustomerController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth:web')->group(function () {
  Route::group(['middleware' => ['role:customer']], function () {
    //Custom Routes
    Route::prefix('customer/')->name('customer.')->group(function () {
      Route::get('dashboard', [CustomerController::class, 'index'])->name('dashboard');
      // Route::post('purchase/{planId}', [CustomerController::class, 'purchase'])->name('purchase');

      Route::post('requestDomain', [CustomerController::class, 'requestDomain'])->name('requestDomain');
      Route::get('cancelDomainRequest/{id}', [CustomerController::class, 'cancelDomainRequest'])->name('cancelDomainRequest');

      Route::get('getBalancePriceForUpgradeAjax', [CustomerController::class, 'getBalancePriceForUpgradeAjax'])->name('getBalancePriceForUpgradeAjax');
      Route::get('getPriceForRenewalAjax', [CustomerController::class, 'getPriceForRenewalAjax'])->name('getPriceForRenewalAjax');
      Route::get('getSubscriptionInfoAjax', [CustomerController::class, 'getSubscriptionInfoAjax'])->name('getSubscriptionInfoAjax');
      // Route::get('upgrade', [CustomerController::class, 'upgrade'])->name('upgrade');

      // Route::post('updateNotificationSettings', [CustomerController::class, 'updateNotificationSettings'])->name('updateNotificationSettings');
      // Route::post('updateBasicInfo', [CustomerController::class, 'updateBasicInfo'])->name('updateBasicInfo');
      // Route::post('updateBusinessInfo', [CustomerController::class, 'updateBusinessInfo'])->name('updateBusinessInfo');

      Route::get('getOrderDetailsAjax/{id}', [CustomerController::class, 'getOrderDetailsAjax'])->name('getOrderDetailsAjax');
      Route::post('getAddUserTotalAmountAjax', [CustomerController::class, 'getAddUserTotalAmountAjax'])->name('getAddUserTotalAmountAjax');
    });
  });
});

