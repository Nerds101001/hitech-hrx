<?php

use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Telephony — the screens (browser-facing)
|--------------------------------------------------------------------------
| Split out of routes/user.php into its own file for one practical reason:
| the production copy of routes/user.php is shipped obfuscated
| (eval(gzinflate(base64_decode(...)))), so it cannot be edited in place
| without decoding, patching minified source and re-obfuscating — three
| chances to break every URL on the site.
|
| Keeping these here means deploying this feature only ever APPENDS one line
| to that file:
|
|     require __DIR__ . '/telephony-web.php';
|
| which leaves the obfuscated payload untouched and is trivial to undo.
|
| The vendor-facing ingest API lives in routes/telephony.php and is required
| from routes/api.php the same way.
*/

Route::middleware([
  'web',
  'auth',
  'role:admin|hr|manager|employee|office_employee|accounts',
  // Temporary, while the feature is being proven. Driven by
  // TELEPHONY_RESTRICT_TO_EMAILS; a no-op when that is empty. Covers the
  // screens only -- the vendor ingest API is authenticated separately and
  // keeps receiving data throughout.
  'telephony.restricted',
])->prefix('user')->name('user.')->group(function () {

  /* Recording playback. The audio never lands on HRX disk and the gateway's
     signed URL never reaches the browser: the controller authorises the
     viewer, fetches a <=300s signed URL server-side, streams the bytes back
     with Range support, and writes an access-log row for every attempt
     including refusals. */
  Route::get('/telephony/recording/{recordingUid}', [\App\Http\Controllers\tenant\TelephonyRecordingController::class, 'stream'])
    ->middleware('role_or_permission:admin|hr|manager|telephony.recording.play')
    ->name('telephony.recording.stream');

  /* Reporting. No role middleware on purpose: scope is decided inside the
     controller by TelephonyCall::visibleTo() — own calls, the manager's team
     including delegated identities, or everything for admin and HR — so an
     employee opening "Calls" sees their own calls rather than a 403. A role
     gate here would take the feature away from the very people whose calls
     it reports. */
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

  /* Administration: the webhook log, the agent mapping the whole feature
     hangs off, the nightly audit, and the unassigned-call queue. Genuinely
     admin-only — the webhook log holds raw vendor payloads, including every
     customer number. */
  Route::prefix('telephony/admin')->name('telephony.admin.')
    ->middleware('role_or_permission:admin|hr|telephony.admin')
    ->group(function () {
      /* Credentials and push volume. Deliberately the first thing in this
         group: generating a key used to mean SSH access and a .env edit, and
         a credential that needs a terminal to rotate is one that never gets
         rotated. */
      Route::get('/connection', [\App\Http\Controllers\tenant\Telephony\TelephonyConnectionController::class, 'index'])
        ->name('connection');
      Route::post('/connection', [\App\Http\Controllers\tenant\Telephony\TelephonyConnectionController::class, 'store'])
        ->name('connection.store');
      Route::put('/connection/{credential}', [\App\Http\Controllers\tenant\Telephony\TelephonyConnectionController::class, 'update'])
        ->name('connection.update');
      Route::post('/connection/{credential}/rotate', [\App\Http\Controllers\tenant\Telephony\TelephonyConnectionController::class, 'rotate'])
        ->name('connection.rotate');
      Route::delete('/connection/{credential}', [\App\Http\Controllers\tenant\Telephony\TelephonyConnectionController::class, 'destroy'])
        ->name('connection.destroy');

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
});
