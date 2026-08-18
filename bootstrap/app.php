<?php

use App\Http\Middleware\LicenseChecker;
use App\Http\Middleware\LoadSettings;
use App\Http\Middleware\LocaleMiddleware;
use App\Http\Middleware\SetHrLayoutMiddleware;
use App\Http\Middleware\TransformApiResponse;
use App\Http\Middleware\MenuPermissionMiddleware;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\Exceptions\UnauthorizedException;
use Spatie\Permission\Middleware\PermissionMiddleware;
use Spatie\Permission\Middleware\RoleMiddleware;
use Spatie\Permission\Middleware\RoleOrPermissionMiddleware;

return Application::configure(basePath: dirname(__DIR__))
  ->withRouting(
    web: __DIR__ . '/../routes/web.php',
    api: __DIR__ . '/../routes/api.php',
    commands: __DIR__ . '/../routes/console.php',
    health: '/up',
  )
  ->withSchedule(function (\Illuminate\Console\Scheduling\Schedule $schedule) {
    $schedule->command('payroll:auto-generate')->lastDayOfMonth('23:00');
    $schedule->command('leave:accrue')->monthlyOn(1, '00:00');
    $schedule->command('leave:reset-yearly')->yearlyOn(4, 1, '00:00');
    /* Keep the audit table small. `audits` grows with every model write and never
       shrinks; this moves anything over 12 months into audits_archive (nothing is
       deleted). Runs on the 2nd so it never contends with the 1st-of-month leave
       and payroll jobs. */
    $schedule->command('hrx:archive-audits --live')->monthlyOn(2, '02:00');
    $schedule->command('leave:daily-digest')->dailyAt('09:00');
    /* Credits back an approved PAID leave day (any type — WFH, half day, full
       day) where the employee also turned up, and cancels the request once
       every day it covers has been attended. Runs before the 09:00 digest so
       that reflects same-day-reconciled numbers, not stale ones. --apply
       makes this a real write, not the dry-run artisan defaults to. */
    $schedule->command('leave:reconcile-attendance --apply')->dailyAt('01:00')->withoutOverlapping();
    /* Digital Library files the upload dialog could not wait for. Hourly and
       overlap-guarded because each file can hold the AI provider for minutes.
       Harmless if it finds nothing, and safe to run by hand. */
    $schedule->command('vault:audit-pending --limit=20')->hourly()->withoutOverlapping();
    /* Fills in the achieved figure for every auto-sourced KPI (data_source
       auto:*) and scores it. Idempotent — overwrites the current period's
       check rather than adding another — so daily is safe for weekly,
       monthly and quarterly assignments alike; each only actually changes
       when its own period rolls over. */
    $schedule->command('kpi:auto-score')->dailyAt('05:00')->withoutOverlapping();
  })
  ->withMiddleware(function (Middleware $middleware) {
    // $middleware->web(LicenseChecker::class);
    $middleware->web(LocaleMiddleware::class);
    $middleware->web(LoadSettings::class);
    $middleware->web(SetHrLayoutMiddleware::class);
    $middleware->web(MenuPermissionMiddleware::class);
    $middleware->web(\App\Http\Middleware\SecurityHardenMiddleware::class);
    $middleware->web(\App\Http\Middleware\OnboardingMiddleware::class);
    $middleware->appendToGroup('api', [
      TransformApiResponse::class,
    ]);
    // HRX's canonical call-control contract (see routes/user.php) is meant to
    // be exercised directly via Postman/curl during local testing, not only
    // through the app's own JS — session auth ('web'+'auth') still applies,
    // this only skips the CSRF double-submit check for this one path.
    $middleware->validateCsrfTokens(except: [
      'user/api/v1/calls/*',
    ]);
    $middleware->alias([
      'role' => RoleMiddleware::class,
      'permission' => PermissionMiddleware::class,
      'role_or_permission' => RoleOrPermissionMiddleware::class,
      'onboarding' => \App\Http\Middleware\OnboardingMiddleware::class,
      'admin.guard' => \App\Http\Middleware\AdminRoleGuard::class,
      'sales.guard' => \App\Http\Middleware\SalesAccessGuard::class,
    ]);
  })
  ->withExceptions(function (Exceptions $exceptions) {
        $exceptions->reportable(function (\Throwable $e) {
            if (app()->environment('production')) {
                try {
                    \Illuminate\Support\Facades\Mail::send([], [], function ($message) use ($e) {
                        $message->to('csenerds@gmail.com')
                            ->subject('[HRX] Production Error: ' . substr($e->getMessage(), 0, 50))
                            ->from(config('mail.from.address'), config('mail.from.name'))
                            ->html(
                                "<h3>Production Error Report</h3>" .
                                "<p><strong>Message:</strong> " . $e->getMessage() . "</p>" .
                                "<p><strong>URL:</strong> " . request()->fullUrl() . "</p>" .
                                "<p><strong>User ID:</strong> " . (auth()->id() ?: 'Guest') . "</p>" .
                                "<p><strong>IP:</strong> " . request()->ip() . "</p>" .
                                "<p><strong>File:</strong> " . $e->getFile() . " (Line: " . $e->getLine() . ")</p>" .
                                "<pre style='background:#f4f4f4;padding:10px;border:1px solid #ddd;'>" . $e->getTraceAsString() . "</pre>"
                            );
                    });
                } catch (\Exception $mailEx) {
                    \Illuminate\Support\Facades\Log::channel('single')->error("Failed to send error email: " . $mailEx->getMessage());
                }
            }
        });

    $exceptions->render(function (AuthenticationException $e, Request $request) {
      // AJAX/fetch callers must get JSON. Redirecting them to the login page
      // makes fetch() follow the 302 and hand back an HTML document, which
      // then blows up as "Unexpected token '<'" in the caller's .json().
      if ($request->is('api/*') || $request->expectsJson()) {
        return response()->json([
          'statusCode' => 401,
          'status' => 'failed',
          'data' => 'Unauthorized',
          'error' => 'Your session has expired. Please refresh the page and sign in again.',
        ], 401);
      }

      return redirect()->guest('auth/login');
    });

    //Validation exception of api response
    $exceptions->render(function (ValidationException $e, Request $request) {
      if ($request->is('api/*')) {
        return response()->json([
          'status' => 'failed',
          'message' => $e->getMessage(),
          'data' => $e->errors()
        ], 422);
      }
    });

    $exceptions->render(function (\Throwable $e, Request $request) {
      if ($e instanceof UnauthorizedException) {
        // Spatie throws this for a missing role AND for an expired session
        // (notLoggedIn). Serving the HTML 403 view to a fetch()/XHR caller is
        // what produced "Unexpected token '<'" on the digital-library upload.
        if ($request->expectsJson()) {
          $guest = auth()->guest();
          return response()->json([
            'status' => 'failed',
            'error' => $guest
              ? 'Your session has expired. Please refresh the page and sign in again.'
              : 'You do not have permission to perform this action.',
          ], $guest ? 401 : 403);
        }

        return response()->view("errors.403", [
          "exception" => $e
        ], 403);
      }
    });

  })->create();
