<?php

namespace App\Providers;

use App\Http\Controllers\SecurityLogController;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

/**
 * Registers the "Login & Security" admin routes from a provider instead of
 * routes/web.php. On production that file is obfuscated and carries drift, so
 * editing it in place is a known hazard — a provider keeps these additive and
 * leaves the obfuscated route files untouched. Mirrors the admin gate used by
 * the Audit Logs routes (web + auth + admin|hr|audit.view).
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
            });
    }
}
