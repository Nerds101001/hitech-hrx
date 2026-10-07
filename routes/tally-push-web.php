<?php

use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Tally Push — the admin screens (browser-facing)
|--------------------------------------------------------------------------
| Split into its own file for the same reason as telephony-web.php: the
| production copy of routes/user.php is shipped obfuscated, so deploying this
| feature only ever APPENDS one line to it —
|
|     require __DIR__ . '/tally-push-web.php';
|
| — leaving the obfuscated payload untouched and trivial to undo. The ingest
| API itself lives in routes/tally_push.php, required from routes/api.php.
|
| Access: the whole group sits behind `tally.viewer` (RestrictTallyAccess),
| so only the emails in TALLY_VIEWER_EMAILS — on prod, one person — reach it;
| everyone else, admins included, gets a 404.
*/

Route::middleware([
    'web',
    'auth',
    'role:admin|hr|accounts|manager|employee|office_employee',
    'tally.viewer',
])->prefix('user')->name('user.')->group(function () {

    Route::prefix('tally-push/admin')->name('tally-push.admin.')
        ->middleware('role_or_permission:admin|accounts|tally.admin')
        ->group(function () {
            // Connection: the office PC's credentials + IP allowlist, and proof the push is arriving.
            Route::get('/connection', [\App\Http\Controllers\tenant\TallyPush\TallyPushConnectionController::class, 'index'])
                ->name('connection');
            Route::post('/connection', [\App\Http\Controllers\tenant\TallyPush\TallyPushConnectionController::class, 'store'])
                ->name('connection.store');
            Route::put('/connection/{credential}', [\App\Http\Controllers\tenant\TallyPush\TallyPushConnectionController::class, 'update'])
                ->name('connection.update');
            Route::post('/connection/{credential}/rotate', [\App\Http\Controllers\tenant\TallyPush\TallyPushConnectionController::class, 'rotate'])
                ->name('connection.rotate');
            Route::delete('/connection/{credential}', [\App\Http\Controllers\tenant\TallyPush\TallyPushConnectionController::class, 'destroy'])
                ->name('connection.destroy');

            // Request log: every inbound hit, accepted or rejected, with time / IP / reason.
            Route::get('/request-log', [\App\Http\Controllers\tenant\TallyPush\TallyPushAdminController::class, 'requestLog'])
                ->name('request-log');
            Route::get('/request-log/{tallyRequest}', [\App\Http\Controllers\tenant\TallyPush\TallyPushAdminController::class, 'showRequest'])
                ->name('request');
        });
});
