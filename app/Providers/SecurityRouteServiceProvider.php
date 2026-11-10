<?php

namespace App\Providers;

use App\Http\Controllers\BackupController;
use App\Http\Controllers\SecurityLogController;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

/**
 * Registers the "Login & Security" admin routes from a provider instead of
 * routes/web.php. On production that file is obfuscated and carries drift, so
 * editing it in place is a known hazard — a provider keeps these additive and
 * leaves the obfuscated route files untouched. Mirrors the admin gate used by
 * the Audit Logs routes (web + auth + admin|hr|audit.view).
 *
 * SECURITY (2026-11-10 audit fix): the outer gate (admin|hr|audit.view) is
 * read-only oversight — login activity and the sessions list. Anything that
 * can pull the full production database (backups/*) is restricted further,
 * to admin only, so the read-only `audit.view` permission and the `hr` role
 * can no longer download a full DB dump or trigger one on demand.
 * `sessions/{id}/revoke` stays reachable by this wider group (so someone
 * can still "sign myself out" from a stray session) — SecurityLogController
 * enforces the real rule: anyone may revoke their OWN session, only admin
 * may revoke someone else's.
 */
class SecurityRouteServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        Route::middleware(['web', 'auth:web', 'role_or_permission:admin|hr|audit.view'])
            ->prefix('security')
            ->name('security.')
            ->group(function () {
                Route::get('login-activity', [SecurityLogController::class, 'index'])->name('index');
                Route::get('sessions', [SecurityLogController::class, 'sessions'])->name('sessions');
                Route::post('sessions/{id}/revoke', [SecurityLogController::class, 'destroySession'])->name('sessions.revoke');

                Route::middleware('role:admin')->group(function () {
                    Route::get('backups', [BackupController::class, 'index'])->name('backups');
                    Route::get('backups/download/{file}', [BackupController::class, 'download'])->name('backups.download')->where('file', '[^/]+');
                    Route::post('backups/run', [BackupController::class, 'runNow'])->name('backups.run');
                });
            });
    }
}
