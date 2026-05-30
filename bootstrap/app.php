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
    $schedule->command('leave:daily-digest')->dailyAt('09:00');
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
    $middleware->alias([
      'role' => RoleMiddleware::class,
      'permission' => PermissionMiddleware::class,
      'role_or_permission' => RoleOrPermissionMiddleware::class,
      'onboarding' => \App\Http\Middleware\OnboardingMiddleware::class,
      'admin.guard' => \App\Http\Middleware\AdminRoleGuard::class,
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
      if ($request->is('api/*')) {
        return response()->json([
          'statusCode' => 401,
          'status' => 'failed',
          'data' => 'Unauthorized'
        ], 401);
      } else {
        return redirect()->guest('auth/login');
      }
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
        return response()->view("errors.403", [
          "exception" => $e
        ], 403);
      }
    });

  })->create();
