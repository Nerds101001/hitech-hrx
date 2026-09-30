<?php

use App\Http\Controllers\Api\TallyPushController;
use Illuminate\Support\Facades\Route;

/**
 * The Tally-to-live-server sync endpoint. Self-authenticating like routes/telephony.php (tally_push.signed
 * verifies the bearer key + HMAC before the controller ever runs), not the app's normal user/JWT auth -- the
 * caller is the local hrx:tally-push command, not a logged-in person.
 */
Route::middleware(['api', 'tally_push.signed', 'throttle:tally_push'])
    ->withoutMiddleware(\App\Http\Middleware\TransformApiResponse::class)
    ->group(function () {
        Route::post('tally-push/sync', [TallyPushController::class, 'sync'])->name('api.tally_push.sync');
        Route::post('tally-push/sync-parties', [TallyPushController::class, 'syncParties'])->name('api.tally_push.sync_parties');
    });
