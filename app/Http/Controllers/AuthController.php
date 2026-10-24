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

  /** One reply for a wrong password and an unknown login alike - see loginPost(). */
  private const BAD_LOGIN = 'The login details are incorrect. After 3 failed attempts the account is locked for 40 minutes.';
  private const LOCKED_MSG = 'Your account has been locked for 40 minutes due to 3 failed attempts. You can request an unlock link by email.';
  private const UNLOCK_SENT = 'If that account is locked, an unlock link has been sent to its email address. It is valid for 15 minutes.';

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

      $user = User::where('email', $request->email)
                  ->orWhere('phone', $request->email)
                  ->orWhere('code', $request->email)
                  ->orWhere('user_name', $request->email)
                  ->first();

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
        // SECURITY (QA fix): removed the local master-password bypass
        // ($request->password === 'password') that granted login to any account.
        if (Hash::check($request->password, $user->password)) {
          // Reset attempts on success
          try {
            $user->update(['login_attempts' => 0, 'locked_until' => null]);
          } catch (Exception $updateError) {
            // Log if column missing, but allow login to continue
            Log::warning("DB Mirror Error: login_attempts column likely missing. " . $updateError->getMessage());
            $user->update(['locked_until' => null]);
          }

          // --- TRUSTED DEVICE CHECK ---
          // Per-user cookie name so people sharing one computer don't
          // overwrite each other's trusted-device token (legacy shared
          // cookie kept as a fallback for devices trusted before this fix)
          $deviceToken = Cookie::get('hrx_device_token_' . $user->id) ?: Cookie::get('hrx_device_token');
          if ($deviceToken) {
              $trusted = UserTrustedDevice::where('user_id', $user->id)
                  ->where('device_token', hash('sha256', (string)$deviceToken))
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
          $otp = random_int(100000, 999999); // SECURITY (M-1): CSPRNG, not predictable rand()
          $user->otp_code = $otp;
          $user->otp_expires_at = now()->addMinutes(10);
          $user->save();

          // Send OTP Notification
          $emailSent = false;
          try {
            if (class_exists('App\Notifications\Auth\OtpNotification')) {
              $user->notify(new OtpNotification($otp));
              $emailSent = true;
            } else {
              Log::warning("OTP Warning: App\Notifications\Auth\OtpNotification class not found. Ensure file is uploaded.");
            }
          } catch (Exception $e) {
            Log::error("OTP Email Failed: " . $e->getMessage());
          }

          if (!$emailSent) {
            return redirect()->back()->with('error', 'We could not send your verification code due to a mail server issue. Please try again in a few minutes or contact your administrator.');
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

            // Send Security Alert to Admin
            try {
                \Illuminate\Support\Facades\Notification::route('mail', config('app.security_alert_email', 'admin@example.com'))
                    ->notify(new \App\Notifications\Auth\SecurityAlertNotification([
                        'email' => $user->email,
                        'ip' => $ip,
                        'reason' => 'Multiple Failed Login Attempts',
                        'action' => 'User Account Locked'
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

            return redirect()->back()->with('error', self::LOCKED_MSG);
          }

          // Same words as an unknown login (below), so the reply never tells
          // anyone whether an account exists. It used to say "Invalid password.
          // You have N attempts left" here and "User not found." there.
          return redirect()->back()->with('error', self::BAD_LOGIN);
        }
      } else {
        // An unknown login gets the same three tries and the same lock message
        // as a real account, or the lock itself would give the account away.
        $key = 'login_fail:' . sha1(strtolower(trim((string) $request->email)));
        $tries = (int) \Illuminate\Support\Facades\Cache::get($key, 0) + 1;
        \Illuminate\Support\Facades\Cache::put($key, $tries, now()->addMinutes(40));
        if ($tries >= 3) {
          return redirect()->back()->with('error', self::LOCKED_MSG);
        }
        return redirect()->back()->with('error', self::BAD_LOGIN);
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

        // Local development only: 123456 stands in for the emailed code, so testing does not
        // depend on a mailbox. It needs BOTH the "local" environment AND a request to localhost,
        // so it cannot work on production even if this file were deployed there. The password
        // step has already been passed by this point. (This replaces the always-on bypass the
        // 2026-08-31 QA pass removed.)
        // LOCAL EDIT (not committed): also honoured through a developer's tunnel, but only for a request that has
        // already passed the TUNNEL_ACCESS_PASSWORD wall -- see App\Http\Middleware\TunnelAccessGate.
        $localBypass = \App\Http\Middleware\TunnelAccessGate::isLocalShortcutAllowed($request)
            && (string) $request->otp === '123456';

        if ($localBypass || ($user->otp_code == $request->otp && $user->otp_expires_at && now()->lt($user->otp_expires_at))) {
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

                // Set secure per-user cookie for 7 days
                Cookie::queue('hrx_device_token_' . $user->id, $rawToken, 60 * 24 * 7, null, null, true, true, false, 'Lax');
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
                    \Illuminate\Support\Facades\Notification::route('mail', config('app.security_alert_email', 'admin@example.com'))
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

      // Enforce 1-minute cooldown between resend requests
      if ($user->otp_expires_at && now()->lt(\Carbon\Carbon::parse($user->otp_expires_at)->subMinutes(9))) {
        return redirect()->back()->with('error', 'Please wait 1 minute before requesting a new code.');
      }

      $otp = random_int(100000, 999999); // SECURITY (M-1): CSPRNG, not predictable rand()
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

  public function showUnlockRequestForm()
  {
      $pageConfigs = ['myLayout' => 'blank'];
      return view('auth.unlock-request', ['pageConfigs' => $pageConfigs]);
  }

  public function requestUnlock(Request $request)
  {
      $email = $request->email ?? User::where('id', session('auth_otp_user_id'))->value('email');
      
      if (!$email) {
          return redirect()->route('login')->with('error', 'Please provide your email to request an unlock.');
      }

      $user = User::where('email', $email)->first();

      if ($user && $user->locked_until && now()->lt($user->locked_until)) {
          // Generate a signed, 15-minute unlock link.
          $unlockUrl = \Illuminate\Support\Facades\URL::temporarySignedRoute(
              'auth.unlock',
              now()->addMinutes(15),
              ['user' => $user->id]
          );

          // Email only. It used to be shown on screen as well, to anyone who
          // typed the email - so a guesser could lock an account, unlock it
          // straight away and carry on guessing, and the lockout protected
          // nothing. Only the account's own inbox gets the link now.
          try {
              $user->notify(new \App\Notifications\Auth\UnlockAccountNotification($unlockUrl));
          } catch (\Throwable $e) {
              \Illuminate\Support\Facades\Log::warning('Unlock email failed: ' . $e->getMessage());
          }

          return redirect()->back()->with('success', self::UNLOCK_SENT);
      }

      if ($user) {
          // A known account that isn't actually locked - tell them directly so they
          // aren't left waiting on an email that will never come. (Deliberately
          // distinguishable from the unknown-email reply below - the user asked for
          // this feedback over keeping unlock-request anonymous for non-locked accounts.)
          return redirect()->back()->with('success', 'Your account is not locked. Please log in.');
      }

      // Unknown email: keep the generic reply, same as the locked case, so nothing
      // here confirms whether an email belongs to an account.
      return redirect()->back()->with('success', self::UNLOCK_SENT);
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
      
      // Authorization check: 
      // 1. Profile pictures are visible to all authenticated employees (we bypass DB query to save connections)
      $isProfilePicture = str_contains($path, \App\Constants\Constants::BaseFolderEmployeeProfile);
      
      // SECURITY (M-3): onboarding images used to be served like public profile
      // pictures (no auth at all), exposing personal onboarding documents to the
      // anonymous internet. Keep them viewable in-app but require the caller to be
      // authenticated. (Ownership is still not enforced for these images so display
      // does not break where the file isn't tracked in the request tables.)
      $isOnboardingImage = false;
      if (!$isProfilePicture && str_contains($path, \App\Constants\Constants::BaseFolderOnboardingDocuments)) {
          $extension = pathinfo($path, PATHINFO_EXTENSION);
          if (in_array(strtolower($extension), ['jpg', 'jpeg', 'png', 'gif', 'webp'])) {
              $isOnboardingImage = true;
          }
      }

      if ($isOnboardingImage && !Auth::check()) {
          abort(403, 'Unauthorized access to secure document.');
      }

      // If it is NOT a profile picture or an onboarding image, boot the auth
      // session and hit the database for the full ownership check.
      if (!$isProfilePicture && !$isOnboardingImage) {
          if (!Auth::check()) {
              abort(403, 'Unauthorized access to secure document.');
          }

          $user = Auth::user();
          $isAdminOrHR = $user->hasRole(['admin', 'hr', 'manager']);

          // Secure ownership check: look up the file path in the database.
          // This prevents guessing attacks based on predictable employee IDs in the path.
          // We check document_requests (uploaded docs) and ProfileUpdateApproval (pending changes).
          $isOwner = \App\Models\DocumentRequest::where('generated_file', $path)
              ->where('user_id', $user->id)
              ->exists();

          if (!$isOwner) {
              // Also check pending profile update approvals (e.g. bank document, profile photo uploads)
              $isOwner = \App\Models\ProfileUpdateApproval::where('user_id', $user->id)
                  ->where('requested_data', 'like', '%' . $path . '%')
                  ->exists();
          }

          if (!$isOwner && !$isAdminOrHR) {
              Log::warning('Secure Document: Access Denied for user ' . $user->id . ' to path: ' . $path);
              abort(403, 'You do not have permission to view this document.');
          }

          // Audit Log: Track who viewed this sensitive file
          Log::info("Security Audit: User ID " . $user->id . " (" . $user->full_name . ") viewed sensitive document: " . $path);
      }

      $decryptedContent = \App\Helpers\FileSecurityHelper::decryptAndGet($path, 'public');

      if (!$decryptedContent) {
          Log::error('Secure Document: File not found or decryption failed.', ['path' => $path]);
          abort(404, 'Document not found or decryption failed.');
      }

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
          // SECURITY (M-3): this route strips SecurityHardenMiddleware, so set the
          // anti-MIME-sniffing header here (content is served inline).
          ->header('X-Content-Type-Options', 'nosniff')
          ->header('Cache-Control', 'private, max-age=604800')
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
      // SECURITY (L-1): match the strong policy used by password reset/change
      // (>=8 chars, mixed case, a number and a symbol) instead of min:6.
      'password' => ['required', \Illuminate\Validation\Rules\Password::min(8)->mixedCase()->numbers()->symbols()],
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
    // The same reply whether or not the email belongs to an account: the stock
    // "We can't find a user with that email address." told anyone which emails
    // are real. (A repeat request inside the throttle window gets it too.)
    if (in_array($status, [Password::RESET_LINK_SENT, Password::INVALID_USER, Password::RESET_THROTTLED], true)) {
      return redirect()->route('login')->with('success', 'If that email belongs to an account, a password reset link has been sent to it. Please check your email.');
    }
    return back()->withErrors(['email' => __($status)]);
  }

  public function resetPassword(string $token)
  {
    $pageConfigs = ['myLayout' => 'blank'];
    return view('auth.reset-password', ['token' => $token, 'pageConfigs' => $pageConfigs]);
  }

  public function resetPasswordPost(Request $request)
  {
    // Trim before validating/storing so a stray copy-paste space can't produce
    // a password the user can't reliably retype, or a false "doesn't match".
    $request->merge([
      'password' => trim((string) $request->input('password')),
      'password_confirmation' => trim((string) $request->input('password_confirmation')),
    ]);

    $request->validate([
      'token' => 'required',
      'email' => 'required|email',
      // Strong password: >=8 chars, upper + lower case, a number and a symbol.
      'password' => ['required', 'confirmed',
        \Illuminate\Validation\Rules\Password::min(8)->mixedCase()->numbers()->symbols()],
    ], [
      'password.min' => 'Password must be at least 8 characters.',
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

  // SECURITY (L-6): removed dead disabledRunDbPatch() — it ran `migrate --force`
  // from a controller action. Migrations are run via CLI during deployment.

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

      if ($user->hasRole(['admin', 'hr', 'manager', 'accounts'])) {
          return redirect()->route('tenant.dashboard')->with('success', 'Welcome back!');
      } else {
          return redirect()->route('user.dashboard.index')->with('success', 'Welcome back!');
      }
  }
}
