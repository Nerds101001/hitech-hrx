<?php

//Open Routes
use App\Http\Controllers\AuthController;
use App\Http\Controllers\BaseController;
use App\Http\Controllers\DashboardController;
use App\Models\User;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Foundation\Auth\EmailVerificationRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;

Route::middleware('web')->group(function () {
  Route::get('/activation', [BaseController::class, 'index'])->name('activation.index');
  Route::post('/activation', [BaseController::class, 'activate'])->name('activation.activate');

  Route::get('/auth/login', [AuthController::class, 'login'])->name('login');
  // SECURITY (H-4): throttle password guessing per account (see the 'login'
  // limiter in AppServiceProvider). Keyed by email so shared-IP offices are fine.
  Route::post('/auth/login', [AuthController::class, 'loginPost'])->middleware('throttle:login')->name('auth.loginPost');
  
  Route::get('two-factor', [AuthController::class, 'showOtpForm'])->name('auth.otp.form');
  Route::post('two-factor/verify', [AuthController::class, 'verifyOtp'])->name('auth.otp.verify');
  Route::post('two-factor/resend', [AuthController::class, 'resendOtp'])->name('auth.otp.resend');

  // Unlock Routes
  Route::get('account/unlock-request', [AuthController::class, 'showUnlockRequestForm'])->name('auth.unlock.request.form');
  Route::post('account/unlock-request', [AuthController::class, 'requestUnlock'])->middleware('throttle:5,15')->name('auth.unlock.request');
  Route::get('account/unlock/{user}', [AuthController::class, 'unlockAccount'])->name('auth.unlock');

  // Secure Document Proxy
  Route::get('secure-document', [AuthController::class, 'serveDocument'])
      ->withoutMiddleware([
          \App\Http\Middleware\LoadSettings::class,
          \App\Http\Middleware\MenuPermissionMiddleware::class,
          \App\Http\Middleware\SetHrLayoutMiddleware::class,
          \App\Http\Middleware\OnboardingMiddleware::class,
          \App\Http\Middleware\SecurityHardenMiddleware::class
      ])
      ->name('auth.document.serve');
  // SECURITY (QA fix): browser-accessible 'db-patch' migration route removed.
  // Run migrations via CLI (`php artisan migrate --force`) during deployment.

  Route::get('/auth/register', [AuthController::class, 'register'])->name('auth.register');
  Route::post('/auth/register', [AuthController::class, 'registerPost'])->name('auth.registerPost');

  Route::get('/', [AuthController::class, 'rootRedirect'])->name('root');

  // GET pages carry no throttle: email clients pre-fetch the reset link, which
  // used to burn the 5/15min limit and lock the real user out ("too many
  // requests"). Only the POST actions are throttled, and generously.
  Route::get('/forgot-password', [AuthController::class, 'forgotPassword'])->middleware('guest')->name('password.request');
  Route::post('/forgot-password', [AuthController::class, 'forgotPasswordPost'])->middleware(['guest', 'throttle:15,10'])->name('password.email');
  Route::get('/reset-password/{token}', [AuthController::class, 'resetPassword'])->middleware('guest')->name('password.reset');
  Route::post('/reset-password', [AuthController::class, 'resetPasswordPost'])->middleware(['guest', 'throttle:15,10'])->name('password.update');

  Route::get('/email/verify', [AuthController::class, 'verifyEmail'])->name('verification.notice');

  Route::post('/email/verification-notification', [AuthController::class, 'verificationSend'])->middleware(['auth', 'throttle:6,1'])->name('verification.send');
  Route::get('/email/verify/{id}/{hash}', [AuthController::class, 'verificationVerify'])->middleware(['auth', 'signed'])->name('verification.verify');

});
