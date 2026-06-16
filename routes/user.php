<?php
use Illuminate\Support\Facades\Route;

use App\Http\Controllers\tenant\users\UserDashboardController;
use App\Http\Controllers\tenant\users\UserPayrollController;
use App\Http\Controllers\tenant\LeaveController;

Route::middleware([
  'web',
  'auth',
  'role:admin|hr|manager|employee|office_employee|accounts'
])->prefix('user')->name('user.')->group(function () {
  Route::get('/dashboard', [UserDashboardController::class, 'index'])->name('dashboard.index');

  // Leaves
    Route::prefix('leaves')->name('leaves.')->group(function () {
     Route::get('/', [UserDashboardController::class, 'leaveIndex'])->name('index');
     Route::post('/store', [UserDashboardController::class, 'leaveStore'])->name('store');
     Route::post('/check-impact', [UserDashboardController::class, 'leaveCheckAjax'])->name('check_impact');
     Route::get('/check-attendance', [UserDashboardController::class, 'leaveAttendanceCheck'])->name('check_attendance');
   });

  // Outdoor Duty (employee self-service)
  Route::prefix('outdoor-duty')->name('outdoor_duty.')->group(function () {
    Route::get('listAjax', [LeaveController::class, 'outdoorDutyListAjax'])->name('listAjax');
    Route::post('storeAjax', [LeaveController::class, 'outdoorDutyStoreAjax'])->name('storeAjax');
    Route::get('getByIdAjax/{id}', [LeaveController::class, 'outdoorDutyGetByIdAjax'])->name('getByIdAjax');
  });

  // Expenses
  Route::prefix('expenses')->name('expenses.')->group(function () {
    Route::get('/', [UserDashboardController::class, 'expenseIndex'])->name('index');
    Route::post('/store', [UserDashboardController::class, 'expenseStore'])->name('store');
  });

  // Attendance
  Route::get('/attendance', [UserDashboardController::class, 'attendanceIndex'])->name('attendance.index');
  Route::get('/attendance/registry', [UserDashboardController::class, 'attendanceRegistryAjax'])->name('attendance.registry');

  // SOS
  Route::get('/sos', [UserDashboardController::class, 'sosIndex'])->name('sos.index');

  // Visits
  Route::get('/visits', [UserDashboardController::class, 'visitIndex'])->name('visits.index');

  // Payroll
  Route::prefix('payroll')->name('payroll.')->group(function () {
      Route::get('/', [UserPayrollController::class, 'index'])->name('index');
      Route::get('/{id}/show-ajax', [UserPayrollController::class, 'showAjax'])->name('show_ajax');
      Route::get('/{id}/download', [UserPayrollController::class, 'download'])->name('download');
  });
});
