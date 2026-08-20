<?php
use Illuminate\Support\Facades\Route;

use App\Http\Controllers\tenant\users\UserDashboardController;
use App\Http\Controllers\tenant\users\UserPayrollController;
use App\Http\Controllers\tenant\LeaveController;

// Delegate Access: log in as a delegated account (header hub) or as any active
// account (super delegate, from the Employee Directory), and exit back.
// Deliberately outside the role list below: the account being entered may hold
// no role at all, and Exit must still work from inside it. Both endpoints do
// their own authorisation in the controller.
Route::middleware(['web', 'auth'])->prefix('user')->name('user.')->group(function () {
  Route::post('/delegation/switch/{id}', [UserDashboardController::class, 'switchToDelegate'])->name('delegation.switch');
  Route::post('/delegation/exit', [UserDashboardController::class, 'exitDelegate'])->name('delegation.exit');
});

Route::middleware([
  'web',
  'auth',
  'role:admin|hr|manager|employee|office_employee|accounts'
])->prefix('user')->name('user.')->group(function () {
  Route::get('/dashboard', [UserDashboardController::class, 'index'])->name('dashboard.index');

  /* Click to call. Dialling is throttled because each request rings a real
     phone and a double-click should not become two call legs; the read-only
     lists are not. Every action re-checks the agent mapping in the controller. */
  Route::prefix('click-to-call')->name('click-to-call.')->group(function () {
    Route::post('/', [\App\Http\Controllers\tenant\ClickToCallController::class, 'dial'])
      ->middleware('throttle:10,1')->name('dial');
    Route::post('/hangup', [\App\Http\Controllers\tenant\ClickToCallController::class, 'hangup'])
      ->middleware('throttle:20,1')->name('hangup');
    Route::post('/dispose', [\App\Http\Controllers\tenant\ClickToCallController::class, 'dispose'])
      ->middleware('throttle:20,1')->name('dispose');
    Route::get('/dispositions', [\App\Http\Controllers\tenant\ClickToCallController::class, 'dispositions'])
      ->name('dispositions');
    Route::get('/sub-dispositions', [\App\Http\Controllers\tenant\ClickToCallController::class, 'subDispositions'])
      ->name('sub-dispositions');
  });

  /* HRX's own canonical call-control API. A vendor-agnostic contract —
     TelephonyCallService is a local mock today; a real vendor adapter
     (Convoque, the current dialer, or anything else) can replace its
     internals later without this contract or any of these routes changing. */
  Route::prefix('api/v1/calls')->name('calls.')->group(function () {
    Route::post('/', [\App\Http\Controllers\tenant\TelephonyCallController::class, 'initiate'])
      ->middleware('throttle:10,1')->name('initiate');
    Route::get('/{callId}', [\App\Http\Controllers\tenant\TelephonyCallController::class, 'show'])
      ->name('show');
    Route::post('/{callId}/hangup', [\App\Http\Controllers\tenant\TelephonyCallController::class, 'hangup'])
      ->middleware('throttle:20,1')->name('hangup');
    Route::post('/{callId}/simulate-customer-hangup', [\App\Http\Controllers\tenant\TelephonyCallController::class, 'simulateCustomerHangup'])
      ->middleware('throttle:20,1')->name('simulate-customer-hangup');
    Route::post('/{callId}/disposition', [\App\Http\Controllers\tenant\TelephonyCallController::class, 'disposition'])
      ->middleware('throttle:20,1')->name('disposition');
    Route::get('/{callId}/recording', [\App\Http\Controllers\tenant\TelephonyCallController::class, 'recording'])
      ->name('recording');
    Route::post('/{callId}/redial', [\App\Http\Controllers\tenant\TelephonyCallController::class, 'redial'])
      ->middleware('throttle:10,1')->name('redial');
  });

  /* Recording playback. The audio never lands on HRX disk and the gateway's
     signed URL never reaches the browser: the controller authorises the
     viewer, fetches a <=300s signed URL server-side, streams the bytes back
     with Range support, and writes an access-log row for every attempt
     including refusals. Placed here rather than in tenant.php so all
     telephony routes stay in one file. */
  Route::get('/telephony/recording/{recordingUid}', [\App\Http\Controllers\tenant\TelephonyRecordingController::class, 'stream'])
    ->middleware('role_or_permission:admin|hr|manager|telephony.recording.play')
    ->name('telephony.recording.stream');

  /* Cross-agent calls report — narrower than the API above, admin/hr only
     since it lists every agent's calls, not just the viewer's own. */
  Route::get('/telephony/calls', [\App\Http\Controllers\tenant\TelephonyCallController::class, 'dashboard'])
    ->middleware('role:admin|hr')->name('telephony.calls.dashboard');

  /* CentroVision telephony reporting.

     Everything built on the data the gateway pushes into
     /api/telephony/v1/events.

     These carry no role middleware on purpose. Scope is decided inside the
     controller by TelephonyCall::visibleTo() -- own calls, the manager's team
     including delegated identities, or everything for admin and HR -- so an
     employee opening "My Calls" sees their own calls rather than a 403. A role
     gate here would take the feature away from the very people whose calls it
     reports. */
  Route::prefix('telephony')->name('telephony.')->group(function () {
    Route::get('/my-calls', [\App\Http\Controllers\tenant\Telephony\TelephonyReportController::class, 'calls'])
      ->name('reports.calls');
    Route::get('/productivity', [\App\Http\Controllers\tenant\Telephony\TelephonyReportController::class, 'productivity'])
      ->name('reports.productivity');
    Route::get('/analytics', [\App\Http\Controllers\tenant\Telephony\TelephonyReportController::class, 'analytics'])
      ->name('reports.analytics');
    /* Declared last so /my-calls and /productivity are not swallowed by the
       {callUid} wildcard. */
    Route::get('/call/{callUid}', [\App\Http\Controllers\tenant\Telephony\TelephonyReportController::class, 'show'])
      ->name('reports.call');
  });

  /* Telephony administration: the webhook log, the agent mapping the whole
     feature hangs off, the nightly audit, and the unassigned-call queue.
     Genuinely admin-only -- the webhook log holds raw vendor payloads,
     including every customer number. */
  Route::prefix('telephony/admin')->name('telephony.admin.')
    ->middleware('role_or_permission:admin|hr|telephony.admin')
    ->group(function () {
      Route::get('/webhook-log', [\App\Http\Controllers\tenant\Telephony\TelephonyAdminController::class, 'webhookLog'])
        ->name('webhook-log');
      Route::get('/webhook-log/{delivery}', [\App\Http\Controllers\tenant\Telephony\TelephonyAdminController::class, 'showDelivery'])
        ->name('delivery');
      Route::post('/webhook-log/{delivery}/replay', [\App\Http\Controllers\tenant\Telephony\TelephonyAdminController::class, 'replayDelivery'])
        ->name('delivery.replay');

      Route::get('/agent-map', [\App\Http\Controllers\tenant\Telephony\TelephonyAdminController::class, 'agentMap'])
        ->name('agent-map');
      Route::post('/agent-map', [\App\Http\Controllers\tenant\Telephony\TelephonyAdminController::class, 'storeAgent'])
        ->name('agent-map.store');
      Route::put('/agent-map/{agentMap}', [\App\Http\Controllers\tenant\Telephony\TelephonyAdminController::class, 'updateAgent'])
        ->name('agent-map.update');
      Route::delete('/agent-map/{agentMap}', [\App\Http\Controllers\tenant\Telephony\TelephonyAdminController::class, 'destroyAgent'])
        ->name('agent-map.destroy');

      Route::get('/reconciliation', [\App\Http\Controllers\tenant\Telephony\TelephonyAdminController::class, 'reconciliation'])
        ->name('reconciliation');
      Route::post('/reconciliation/run', [\App\Http\Controllers\tenant\Telephony\TelephonyAdminController::class, 'runReconciliation'])
        ->name('reconciliation.run');

      Route::get('/exceptions', [\App\Http\Controllers\tenant\Telephony\TelephonyAdminController::class, 'exceptions'])
        ->name('exceptions');
      Route::post('/exceptions/assign', [\App\Http\Controllers\tenant\Telephony\TelephonyAdminController::class, 'assignException'])
        ->name('exceptions.assign');
    });

  // Announcements targeted at this employee (department / location / role / team)
  Route::get('/announcements', [\App\Http\Controllers\tenant\AnnouncementController::class, 'myFeed'])->name('announcements.feed');

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
