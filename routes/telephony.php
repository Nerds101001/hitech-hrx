<?php

use App\Http\Controllers\Api\Telephony\TelephonyIngestController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Telephony ingest API  (vendor -> HRX)
|--------------------------------------------------------------------------
| Published to CentroVision. Every route is guarded by VerifyTelephonySignature
| (bearer key + HMAC + clock skew + nonce + IP allowlist) before the controller
| is reached, so an unsigned request never touches the database.
|
| Wire this up in routes/api.php with:
|     require __DIR__ . '/telephony.php';
*/

Route::prefix('telephony/v1')
  ->middleware(['api', 'telephony.signed', 'throttle:telephony'])
  // HRX's global api group runs TransformApiResponse, which camelCases every
  // key of a successful JSON body. That is fine for the mobile app, but this
  // is a published contract: the vendor is coded against `event_id` and
  // `api_version`, and silently renaming them to `eventId` / `apiVersion`
  // would break their parser with no error anywhere. Opt out here.
  ->withoutMiddleware(\App\Http\Middleware\TransformApiResponse::class)
  ->group(function () {
    Route::get('/health', [TelephonyIngestController::class, 'health']);

    Route::post('/events', [TelephonyIngestController::class, 'event']);
    Route::post('/events/batch', [TelephonyIngestController::class, 'batch']);
    Route::post('/recordings/ready', [TelephonyIngestController::class, 'recordingReady']);
    Route::post('/agent-sessions', [TelephonyIngestController::class, 'agentSession']);
    Route::post('/cdr/import', [TelephonyIngestController::class, 'importCdr']);
  });
