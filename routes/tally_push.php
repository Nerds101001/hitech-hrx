<?php

use App\Http\Controllers\Api\TallyPushController;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Route;

/**
 * The Tally-to-live-server sync endpoint. Self-authenticating like routes/telephony.php (tally_push.signed
 * verifies the bearer key + HMAC before the controller ever runs), not the app's normal user/JWT auth -- the
 * caller is the local hrx:tally-push command, not a logged-in person.
 */

// The throttle:tally_push limiter is defined here (rather than in a provider) so this feature deploys as a
// self-contained set of files that only ever APPENDS a require to routes/api.php -- no change to the obfuscated
// provider on production. 20 pushes/min per IP is far above a 2-hourly sync yet caps a flood of refusals.
RateLimiter::for('tally_push', fn ($request) => Limit::perMinute(20)->by($request->ip()));

Route::middleware(['api', 'tally_push.signed', 'throttle:tally_push'])
    ->withoutMiddleware(\App\Http\Middleware\TransformApiResponse::class)
    ->group(function () {
        Route::post('tally-push/sync', [TallyPushController::class, 'sync'])->name('api.tally_push.sync');
        Route::post('tally-push/sync-parties', [TallyPushController::class, 'syncParties'])->name('api.tally_push.sync_parties');
        // Bill-wise outstanding (receivables) as a full-table replace -- a snapshot, like sync-parties.
        Route::post('tally-push/sync-outstanding', [TallyPushController::class, 'syncOutstanding'])->name('api.tally_push.sync_outstanding');
        // Read-only: the customer names the party sync is narrowed to. Signed like the posts (the signature covers an empty body).
        Route::get('tally-push/customer-names', [TallyPushController::class, 'customerNames'])->name('api.tally_push.customer_names');

        // Backfill queue: a viewer requests a month on the HRX admin page (web route, below), the office PC claims the
        // oldest pending job here, runs hrx:tally-push for its range, and writes progress back. The live server never
        // reaches Tally itself -- these two endpoints are the hand-off between the page and the office PC.
        Route::post('tally-push/jobs/claim', [TallyPushController::class, 'claimJob'])->name('api.tally_push.jobs.claim');
        Route::post('tally-push/jobs/{id}', [TallyPushController::class, 'jobUpdate'])->name('api.tally_push.jobs.update');
    });
