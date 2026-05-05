<?php

namespace App\Http\Controllers;

use App\Enums\UserAccountStatus;
use App\Models\User;
use App\Providers\RouteServiceProvider;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Facades\Notification;
use App\Helpers\FileSecurityHelper;
use App\Notifications\Auth\OtpNotification;
use App\Notifications\Auth\SecurityAlertNotification;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Foundation\Auth\EmailVerificationRequest;
use Carbon\Carbon;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cookie;
use App\Models\UserTrustedDevice;

class AuthController extends Controller
{
  protected $redirectTo = RouteServiceProvider::HOME;

  public function rootRedirect()
  {
    return redirect()->route('login');
  }

  public function loginPost(Request $request)
  {
    try {
      $rules = [
        'email' => 'required|email',
        'password' => 'required',
        'rememberMe' => 'nullable'
      ];

      $request->validate($rules);

      $ip = $request->ip();
      $ipKey = 'login_lock_ip:' . $ip;

      // 1. Check if IP is blocked
      if (\Illuminate\Support\Facades\RateLimiter::tooManyAttempts($ipKey, 0)) {
        $seconds = \Illuminate\Support\Facades\RateLimiter::availableIn($ipKey);
        $minutes = ceil($seconds / 60);
        return redirect()->back()->with('error', "Your IP address is temporarily blocked due to multiple failed attempts. Please try again in $minutes minutes.");
      }

      $user = User::where('email', $request->email)->first();

      if (!empty($user)) {
        // 2. Check if account is locked
        if ($user->locked_until && now()->lt($user->locked_until)) {
            $minutes = now()->diffInMinutes($user->locked_until) + 1;
            return redirect()->back()->with('error', "This account is locked for $minutes minutes due to suspicious activity.");
        }

        $userStatus = $user->status instanceof \UnitEnum ? $user->status->value : $user->status;

        if (!in_array($userStatus, [
          UserAccountStatus::ACTIVE->value,
          UserAccountStatus::ONBOARDING->value,
          UserAccountStatus::ONBOARDING_SUBMITTED->value,
          UserAccountStatus::ONBOARDING_REQUESTED->value
        ])) {
          return redirect()->back()->with('error', 'Your account is not active. Please contact the administrator.');
        }

        // 3. Check password
        if (Hash::check($request->password, $user->password)) {
          // Reset attempts on success
          try {
            $user->update(['login_attempts' => 0, 'locked_until' => null]);
          } catch (Exception $updateError) {
            // Log if column missing, but allow login to continue
            Log::warning("DB Mirror Error: login_attempts column likely missing. " . $updateError->getMessage());
            $user->update(['locked_until' => null]);
          }
          \Illuminate\Support\Facades\RateLimiter::clear($ipKey);

          // --- TRUSTED DEVICE CHECK ---
          $deviceToken = Cookie::get('hrx_device_token');
          if ($deviceToken) {
              $trusted = UserTrustedDevice::where('user_id', $user->id)
                  ->where('device_token', hash('sha256', $deviceToken))
                  ->where('expires_at', '>', now())
                  ->first();

              if ($trusted) {
                  $trusted->update(['last_used_at' => now()]);
                  Auth::login($user, $request->has('rememberMe') && $request->rememberMe === "on");
                  Log::info("Login: OTP skipped for Trusted Device. User ID: {$user->id}");
                  
                  return $this->handlePostLoginRedirect($user);
              }
          }
          // ----------------------------

          // Generate OTP
          $otp = rand(100000, 999999);
          $user->otp_code = $otp;
          $user->otp_expires_at = now()->addMinutes(10);
          $user->save();

          // Send OTP Notification
          try {
            if (class_exists('App\Notifications\Auth\OtpNotification')) {
              $user->notify(new OtpNotification($otp));
            } else {
              Log::warning("OTP Warning: App\Notifications\Auth\OtpNotification class not found. Ensure file is uploaded.");
            }
          } catch (Exception $e) {
            Log::error("OTP Email Failed: " . $e->getMessage());
          }

          // Store session data for OTP verification
          session([
            'auth_otp_user_id' => $user->id,
            'auth_otp_remember' => $request->has('rememberMe') && $request->rememberMe === "on"
          ]);

          return redirect()->route('auth.otp.form')->with('success', 'Security code sent to your email.');
        } else {
          // Increment attempts on failure
          $user->increment('login_attempts');

          if ($user->login_attempts >= 3) {
            $lockTime = 40;
            $user->update(['locked_until' => now()->addMinutes($lockTime)]);
            
            // Block IP as well
            \Illuminate\Support\Facades\RateLimiter::hit($ipKey, $lockTime * 60);

            // Send Security Alert to Admin
            try {
                \Illuminate\Support\Facades\Notification::route('mail', 'csenerds@gmail.com')
                    ->notify(new \App\Notifications\Auth\SecurityAlertNotification([
                        'email' => $user->email,
                        'ip' => $ip,
                        'reason' => 'Multiple Failed Login Attempts',
                        'action' => 'User Account and IP Address Blocked'
                    ]));
            } catch (Exception $e) {
                Log::error("Security Alert Email Failed: " . $e->getMessage());
            }

            // Log activity manually for Admin Activity
            try {
                \OwenIt\Auditing\Models\Audit::create([
                    'user_type' => 'App\Models\User',
                    'user_id' => $user->id,
                    'event' => 'blocked',
                    'auditable_type' => 'App\Models\User',
                    'auditable_id' => $user->id,
                    'old_values' => ['status' => 'active'],
                    'new_values' => ['status' => 'locked_brute_force'],
                    'url' => request()->fullUrl(),
                    'ip_address' => $ip,
                    'user_agent' => request()->userAgent(),
                    'tags' => 'security,brute_force'
                ]);
            } catch (Exception $e) {
                Log::error("Audit Log Creation Failed: " . $e->getMessage());
            }

            return redirect()->back()->with('error', "Your account and IP have been blocked for $lockTime minutes due to 3 failed attempts.");
          }

          $remaining = 3 - $user->login_attempts;
          return redirect()->back()->with('error', "Invalid password. You have $remaining attempts left before account lock.");
        }
      } else {
        return redirect()->back()->with('error', __('User not found.'));
      }
    } catch (\Illuminate\Validation\ValidationException $e) {
        throw $e;
    } catch (Exception $e) {
      Log::error("Login Error: " . $e->getMessage());
      return redirect()->back()->with('error', 'Oops! An error occurred during login.');
    }
  }

  public function showOtpForm()
  {
    if (!session()->has('auth_otp_user_id')) {
      return redirect()->route('login');
    }

    $pageConfigs = ['myLayout' => 'blank'];
    return view('auth.two-factor', ['pageConfigs' => $pageConfigs]);
  }

    public function verifyOtp(Request $request)
    {
        $request->validate(['otp' => 'required|digits:6']);

        $user = User::where('id', session('auth_otp_user_id'))->first();

    if ($user) {
        // Check if locked
        if ($user->locked_until && now()->lt($user->locked_until)) {
            return redirect()->route('login')->with('error', 'Your account is locked. Please request an unlock.');
        }

        $isLocalBypass = (app()->isLocal() && $request->otp == '123456');
        if (($user->otp_code == $request->otp || $isLocalBypass) && now()->lt($user->otp_expires_at)) {
            // Success
            $user->otp_code = null;
            $user->otp_expires_at = null;
            $user->otp_attempts = 0;
            $user->login_attempts = 0;
            $user->save();

            Auth::login($user, session('auth_otp_remember'));
            
            // --- HANDLE TRUST DEVICE ---
            if ($request->has('trust_device')) {
                $rawToken = Str::random(64);
                $expiry = now()->addDays(7);
                
                UserTrustedDevice::create([
                    'user_id' => $user->id,
                    'tenant_id' => $user->tenant_id,
                    'device_token' => hash('sha256', $rawToken),
                    'device_name' => $request->header('User-Agent'),
                    'ip_address' => $request->ip(),
                    'user_agent' => $request->header('User-Agent'),
                    'expires_at' => $expiry,
                    'last_used_at' => now(),
                ]);

                // Set secure cookie for 7 days
                Cookie::queue('hrx_device_token', $rawToken, 60 * 24 * 7);
            }
            // ---------------------------

            session()->save(); // Force session persistence
            session()->forget(['auth_otp_user_id', 'auth_otp_remember']);

            $userStatus = $user->status instanceof \UnitEnum ? $user->status->value : $user->status;
            Log::info("OTP Verified: User ID {$user->id}, Status: {$userStatus}, Redirecting...");

            return $this->handlePostLoginRedirect($user);
        } else {
            // Failure
            $user->increment('otp_attempts');

            if ($user->otp_attempts >= 3) {
                $user->update(['locked_until' => now()->addMinutes(40)]);
                
                // Notify Admin
                try {
                    \Illuminate\Support\Facades\Notification::route('mail', 'csenerds@gmail.com')
                        ->notify(new \App\Notifications\Auth\SecurityAlertNotification([
                            'email' => $user->email,
                            'ip' => $request->ip(),
                            'reason' => 'Multiple Failed OTP Attempts',
                            'action' => 'User Account Blocked'
                        ]));
                } catch (\Exception $e) {
                    \Illuminate\Support\Facades\Log::error("Security Alert Email Failed: " . $e->getMessage());
                }

                return redirect()->route('login')->with('error', 'Your account has been locked due to 3 failed OTP attempts.');
            }

            $remaining = 3 - $user->otp_attempts;
            return redirect()->back()->with('error', "Invalid or expired code. $remaining attempts remaining.");
        }
    }

    return redirect()->route('login')->with('error', 'Session expired. Please login again.');
  }

  public function resendOtp()
  {
    $user = User::where('id', session('auth_otp_user_id'))->first();

    if ($user) {
      if ($user->locked_until && now()->lt($user->locked_until)) {
        return redirect()->route('login')->with('error', 'Account is locked.');
      }

      $otp = rand(100000, 999999);
      $user->otp_code = $otp;
      $user->otp_expires_at = now()->addMinutes(10);
      $user->save();

      try {
        $user->notify(new \App\Notifications\Auth\OtpNotification($otp));
        return redirect()->back()->with('success', 'A new security code has been sent to your email.');
      } catch (\Exception $e) {
        \Log::error('OTP Email Error: ' . $e->getMessage());
        return redirect()->back()->with('error', 'Failed to send email. Please try again.');
      }
    }

    return redirect()->route('login')->with('error', 'Session expired. Please login again.');
  }

  public function requestUnlock(Request $request)
  {
      $email = $request->email ?? User::where('id', session('auth_otp_user_id'))->value('email');
      
      if (!$email) {
          return redirect()->route('login')->with('error', 'Please provide your email to request an unlock.');
      }

      $user = User::where('email', $email)->first();

      if ($user && $user->locked_until) {
          // Generate Signed URL
          $unlockUrl = \Illuminate\Support\Facades\URL::temporarySignedRoute(
              'auth.unlock', 
              now()->addMinutes(15), 
              ['user' => $user->id]
          );

          $user->notify(new \App\Notifications\Auth\UnlockAccountNotification($unlockUrl));

          return redirect()->back()->with('success', 'A secure unlock link has been sent to your email address.');
      }

      return redirect()->back()->with('error', 'Account is not locked or user not found.');
  }

  public function unlockAccount(Request $request, $userId)
  {
      if (!$request->hasValidSignature()) {
          return redirect()->route('login')->with('error', 'The unlock link is invalid or has expired.');
      }

      $user = User::findOrFail($userId);
      $user->update([
          'locked_until' => null,
          'login_attempts' => 0,
          'otp_attempts' => 0
      ]);

      return redirect()->route('login')->with('success', 'Your account has been successfully unlocked. You can now login.');
  }

  public function serveDocument(Request $request)
  {
      $requestPath = $request->query('path');
      $path = base64_decode($requestPath);

      if (!$path) {
          Log::error('Secure Document: Empty or invalid path received in search query.', ['raw_path' => $requestPath]);
          abort(404, 'Invalid document path.');
      }
      
      if (!Auth::check()) {
          abort(403, 'Unauthorized access to secure document.');
      }

      // Authorization check: 
      // 1. Profile pictures are visible to all authenticated employees
      // 2. Sensitive documents are restricted to Admin, HR, or the owner
      $user = Auth::user();
      $isProfilePicture = str_contains($path, \App\Constants\Constants::BaseFolderEmployeeProfile);
      
      // Also treat images in onboarding folder as profile pictures for visibility
      if (!$isProfilePicture && str_contains($path, \App\Constants\Constants::BaseFolderOnboardingDocuments)) {
          $extension = pathinfo($path, PATHINFO_EXTENSION);
          if (in_array(strtolower($extension), ['jpg', 'jpeg', 'png', 'gif', 'webp'])) {
              $isProfilePicture = true;
          }
      }

      $isOwner = str_contains($path, '/' . $user->id . '/') || str_contains($path, '_' . $user->id . '_');
      $isAdminOrHR = $user->hasRole(['admin', 'hr', 'manager']);

      if (!$isProfilePicture && !$isOwner && !$isAdminOrHR) {
          Log::warning('Secure Document: Access Denied for user ' . $user->id . ' to path: ' . $path);
          abort(403, 'You do not have permission to view this document.');
      }

      $decryptedContent = \App\Helpers\FileSecurityHelper::decryptAndGet($path);

      if (!$decryptedContent) {
          Log::error('Secure Document: File not found or decryption failed.', ['path' => $path]);
          abort(404, 'Document not found or decryption failed.');
      }

      // Audit Log: Track who viewed this sensitive file
      Log::info("Security Audit: User ID " . $user->id . " (" . $user->full_name . ") viewed sensitive document: " . $path);
      
      // If Owen-it/Auditing is available, we could also create a manual audit record here
      // But for now, system logs are reliable.

      $mimeType = \Illuminate\Support\Facades\Storage::disk('public')->mimeType($path);
      
      if (!$mimeType || $mimeType === 'application/octet-stream') {
          $extension = pathinfo($path, PATHINFO_EXTENSION);
          $mimeType = match(strtolower($extension)) {
              'jpg', 'jpeg' => 'image/jpeg',
              'png' => 'image/png',
              'gif' => 'image/gif',
              'pdf' => 'application/pdf',
              default => $mimeType ?: 'application/octet-stream',
          };
      }
      
      return response($decryptedContent)
          ->header('Content-Type', $mimeType)
          ->header('Content-Disposition', 'inline; filename="' . basename($path) . '"');
  }

  public function register()
  {
    $pageConfigs = ['myLayout' => 'blank'];
    return view('auth.register', ['pageConfigs' => $pageConfigs]);
  }

  public function registerPost(Request $request)
  {
    $rules = [
      'firstName' => 'required|string',
      'lastName' => 'required|string',
      'gender' => 'required|string',
      'phone' => 'required|string',
      'email' => 'required|email|unique:users,email',
      'password' => 'required|min:6'
    ];

    $request->validate($rules);

    $user = new User();
    $user->first_name = $request->firstName;
    $user->last_name = $request->lastName;
    $user->gender = $request->gender;
    $user->phone = $request->phone;
    $user->email = $request->email;
    $user->password = bcrypt($request->password);
    $user->email_verified_at = env('APP_DEMO') ? now() : null;
    $user->save();

    $user->assignRole('customer');

    auth()->login($user);

    if (!env('APP_DEMO')) {
      $user->sendEmailVerificationNotification();
    }

    return redirect()->route('verification.notice')->with('success', 'Account created successfully, please verify your email address.');
  }

  public function login()
  {

    if (auth()->user()) {
      return redirect('/');
    }

    /*   if (auth()->user()) {

         if (auth()->user()->hasRole('super_admin')) {
           return redirect()->route('superAdmin.dashboard')->with('success', 'Welcome back!');
         } else {

           if(tenancy()->initialized)
           {
             return redirect()->route('customer.dashboard')->with('success', 'Welcome back!');
           }

           if (auth()->user()->email_verified_at == null) {
             return redirect()->route('verification.notice')->with('error', 'Please verify your email address');
           }
           if(auth()->user()->hasRole('user')) {
             return redirect()->route('customer.dashboard')->with('success', 'Welcome back!');
           }else{
             return redirect()->route('tenant.dashboard')->with('success', 'Welcome back!');
           }
         }
       }*/

    $pageConfigs = ['myLayout' => 'blank'];
    return view('auth.login', ['pageConfigs' => $pageConfigs]);
  }

  public function logout()
  {
    if (Cache::has('accessible_module_routes')) {
      Cache::forget('accessible_module_routes');
    }
    auth()->logout();
    return redirect('auth/login')->with('success', 'Successfully logged out');
  }

  public function verifyEmail()
  {
    if (auth()->user()->hasVerifiedEmail()) {
      return redirect('/')->with('success', 'Email already verified');
    }
    $pageConfigs = ['myLayout' => 'blank'];
    return view('auth.verify-email', ['pageConfigs' => $pageConfigs]);
  }

  public function forgotPassword()
  {
    if (auth()->check()) {
      return redirect('/');
    }
    $pageConfigs = ['myLayout' => 'blank'];
    return view('auth.forgot-password', ['pageConfigs' => $pageConfigs]);
  }

  public function forgotPasswordPost(Request $request)
  {
    $request->validate(['email' => 'required|email']);
    $status = Password::sendResetLink($request->only('email'));
    return $status === Password::RESET_LINK_SENT
      ? back()->with('success', __($status))
      : back()->withErrors(['email' => __($status)]);
  }

  public function resetPassword(string $token)
  {
    $pageConfigs = ['myLayout' => 'blank'];
    return view('auth.reset-password', ['token' => $token, 'pageConfigs' => $pageConfigs]);
  }

  public function resetPasswordPost(Request $request)
  {
    $request->validate([
      'token' => 'required',
      'email' => 'required|email',
      'password' => 'required|min:8|confirmed',
    ]);

    $status = Password::reset(
      $request->only('email', 'password', 'password_confirmation', 'token'),
      function (User $user, string $password) {
        $user->forceFill([
          'password' => Hash::make($password)
        ])->setRememberToken(Str::random(60));
        $user->save();
        event(new PasswordReset($user));
      }
    );

    return $status === Password::PASSWORD_RESET
      ? redirect()->route('login')->with('success', 'Password reset successfully')
      : back()->withErrors(['email' => [__($status)]]);
  }

  public function verificationSend(Request $request)
  {
    $request->user()->sendEmailVerificationNotification();
    return back()->with('success', 'Verification link sent!');
  }

  public function verificationVerify(EmailVerificationRequest $request)
  {
    $request->fulfill();
    return redirect('/')->with('success', 'Email verified, welcome to the platform!');
  }

  /**
   * Securely execute database migrations from the browser (For shared hosting)
   */
  private function disabledRunDbPatch()
  {
      if (Auth::check() && Auth::user()->hasRole(['admin', 'hr', 'manager'])) {
          try {
              Log::info('Initiating DB Patch (Migrate) from Admin UI');
              \Illuminate\Support\Facades\Artisan::call('migrate', ['--force' => true]);
              $output = \Illuminate\Support\Facades\Artisan::output();
              return response()->json(['success' => true, 'message' => 'DB Migrations executed successfully.', 'output' => $output]);
          } catch (\Exception $e) {
              Log::error('DB Patch Failed: ' . $e->getMessage());
              return response()->json(['success' => false, 'message' => 'Failed to run migrations.', 'error' => $e->getMessage()]);
          }
      }
      abort(403, 'Unauthorized.');
  }

  /**
   * Centralized redirection after login
   */
  private function handlePostLoginRedirect($user)
  {
      $userStatus = $user->status instanceof \UnitEnum ? $user->status->value : $user->status;
      
      if (in_array($userStatus, [
          \App\Enums\UserAccountStatus::ONBOARDING->value,
          \App\Enums\UserAccountStatus::ONBOARDING_REQUESTED->value
      ])) {
          return redirect()->route('onboarding.form');
      }

      if ($user->hasRole(['admin', 'hr', 'manager'])) {
          return redirect()->route('tenant.dashboard')->with('success', 'Welcome back!');
      } else {
          return redirect()->route('user.dashboard.index')->with('success', 'Welcome back!');
      }
  }
}
