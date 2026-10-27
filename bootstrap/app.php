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
    /* Nightly proof that HRX and the CentroVision CDR agree about yesterday,
       and the only thing that recovers calls a webhook never delivered. 02:00
       is off-peak for the gateway, whose CDR screens are slow under load. */
    $schedule->command('telephony:reconcile')->dailyAt('02:00')->withoutOverlapping();
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
       auto:*) and scores it. Idempotent — overwrites that period's check
       rather than adding another — so daily is safe for weekly, monthly and
       quarterly assignments alike.
       --months=2 re-scores last month as well as this one, because the data
       behind a month keeps moving after the month ends: the sales report
       stays open to the 5th and is often reopened for weeks, and the owner of
       a manual figure reports it in the first days of the following month.
       Scoring a month once on the 1st froze half-entered numbers into
       people's appraisals. */
    $schedule->command('kpi:auto-score --months=2')->dailyAt('05:00')->withoutOverlapping();
    /* 90-Day Goals: every morning, close rounds that have ended, start a round
       for anyone in Sales without one, open the week's check-ins, pick up Sales
       Report orders entered late, and put the "submit your status" task on each
       person's list on its due day. Idempotent, so daily is safe; Monday-only
       missed rounds that start on other weekdays. */
    $schedule->command('goals:weekly')->dailyAt('06:00')->withoutOverlapping();
    // Hourly so it catches the ~12h-before-close mark whatever time a window ends.
    $schedule->command('ninety-day:remind-add-customers')->hourly()->withoutOverlapping();
  })
  ->withMiddleware(function (Middleware $middleware) {
    // Passphrase wall for a developer's temporary tunnel to a LOCAL instance (see TunnelAccessGate). Inert
    // unless TUNNEL_ACCESS_PASSWORD is set, which only a developer's own .env does. (Anything that reads .env
    // has to happen inside the gate at request time, not here: this closure runs before .env is loaded.)
    $middleware->prepend(\App\Http\Middleware\TunnelAccessGate::class);
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
    // SECURITY (M-2): the telephony call-control API (user/api/v1/calls/*) is
    // session-authenticated and state-changing (initiate/hangup/disposition/
    // redial). It previously skipped CSRF, so a cross-site page could drive it
    // with the victim's session cookie (place calls, change dispositions). The
    // dashboard's fetch() calls now send X-CSRF-TOKEN, so the exemption is
    // removed and default CSRF protection applies to every path. (Postman/curl
    // testing must now include the CSRF token + cookie.)
    $middleware->alias([
      'role' => RoleMiddleware::class,
      'permission' => PermissionMiddleware::class,
      'role_or_permission' => RoleOrPermissionMiddleware::class,
      'onboarding' => \App\Http\Middleware\OnboardingMiddleware::class,
      'admin.guard' => \App\Http\Middleware\AdminRoleGuard::class,
      'sales.guard' => \App\Http\Middleware\SalesAccessGuard::class,
      'telephony.signed' => \App\Http\Middleware\VerifyTelephonySignature::class,
      'telephony.restricted' => \App\Http\Middleware\RestrictTelephonyAccess::class,
      'tally_push.signed' => \App\Http\Middleware\VerifyTallyPushSignature::class,
      'tally.viewer' => \App\Http\Middleware\RestrictTallyAccess::class,
      'handover.viewer' => \App\Http\Middleware\RestrictHandoverAccess::class,
      'restrict.email' => \App\Http\Middleware\RestrictToEmail::class,
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
