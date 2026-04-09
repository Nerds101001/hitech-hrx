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
  Route::post('/auth/login', [AuthController::class, 'loginPost'])->name('auth.loginPost');
  
  Route::get('two-factor', [AuthController::class, 'showOtpForm'])->name('auth.otp.form');
  Route::post('two-factor/verify', [AuthController::class, 'verifyOtp'])->name('auth.otp.verify');
  Route::post('two-factor/resend', [AuthController::class, 'resendOtp'])->name('auth.otp.resend');

  // Unlock Routes
  Route::post('account/unlock-request', [AuthController::class, 'requestUnlock'])->name('auth.unlock.request');
  Route::get('account/unlock/{user}', [AuthController::class, 'unlockAccount'])->name('auth.unlock');

  // Secure Document Proxy
  Route::get('secure-document', [AuthController::class, 'serveDocument'])->middleware('auth')->name('auth.document.serve');
  Route::get('db-patch', [AuthController::class, 'runDbPatch'])->middleware('auth')->name('auth.db_patch');

  Route::get('/auth/register', [AuthController::class, 'register'])->name('auth.register');
  Route::post('/auth/register', [AuthController::class, 'registerPost'])->name('auth.registerPost');

  Route::get('/', [AuthController::class, 'rootRedirect'])->name('root');

  Route::get('/forgot-password', [AuthController::class, 'forgotPassword'])->middleware('guest')->name('password.request');
  Route::post('/forgot-password', [AuthController::class, 'forgotPasswordPost'])->middleware('guest')->name('password.email');
  Route::get('/reset-password/{token}', [AuthController::class, 'resetPassword'])->middleware('guest')->name('password.reset');




  Route::post('/reset-password', [AuthController::class, 'resetPasswordPost'])->middleware('guest')->name('password.update');

  Route::get('/email/verify', [AuthController::class, 'verifyEmail'])->name('verification.notice');

  Route::post('/email/verification-notification', [AuthController::class, 'verificationSend'])->middleware(['auth', 'throttle:6,1'])->name('verification.send');
  Route::get('/email/verify/{id}/{hash}', [AuthController::class, 'verificationVerify'])->middleware(['auth', 'signed'])->name('verification.verify');

});
