<?php

namespace App\Providers;

use Illuminate\Support\Facades\URL;
use Illuminate\Support\Facades\Vite;
use Illuminate\Support\ServiceProvider;
use Illuminate\Pagination\Paginator;
use App\Enums\UserAccountStatus;

class AppServiceProvider extends ServiceProvider
{
  /**
   * Register any application services.
   */
  public function register(): void
  {
    if ($this->app->environment('production')) {
      URL::forceScheme('https');
      // SECURITY (H-3): pin the root URL to APP_URL so generated absolute links
      // (notably the password-reset URL, which is built with url()) cannot be
      // poisoned via the Host header. No TrustHosts is configured, so url()
      // would otherwise take the host from an attacker-supplied Host header,
      // letting a reset email point at an attacker's domain (token capture ->
      // account takeover). Production-only, so local dev is unaffected.
      URL::forceRootUrl(config('app.url'));
    }
  }

  /**
   * Bootstrap any application services.
   */
  public function boot(): void
  {
    /* Telephony ingest. A burst from the gateway - a backlog drain after an
       outage, or a busy hour on the floor - must not exhaust the web workers
       every other page depends on. 600/min is ~10 events a second, well above
       any real call volume here and well below anything that hurts. */
    \Illuminate\Support\Facades\RateLimiter::for('telephony', function ($request) {
      return \Illuminate\Cache\RateLimiting\Limit::perMinute(600)->by($request->ip());
    });

    /* The Tally push has exactly one legitimate caller (the local puller, run on a schedule a few times a
       day), so this is about catching a misbehaving/looping client, not shaping real traffic. */
    \Illuminate\Support\Facades\RateLimiter::for('tally_push', function ($request) {
      return \Illuminate\Cache\RateLimiting\Limit::perMinute(20)->by($request->ip());
    });

    /* SECURITY (H-4): cap password guessing per account. This complements the
       3-attempt account lockout so that even if the lockout is lifted (e.g. via
       the self-service unlock link) an attacker still cannot retry rapidly.
       Keyed by EMAIL, not IP, so it is safe behind the shared CDN/proxy IP and
       does not throttle a whole office logging in from one NAT address. */
    \Illuminate\Support\Facades\RateLimiter::for('login', function ($request) {
      $email = strtolower(trim((string) $request->input('email')));
      $key = $email !== '' ? 'login:' . $email : 'login-ip:' . $request->ip();
      return \Illuminate\Cache\RateLimiting\Limit::perMinute(5)->by($key);
    });

    // Mobile app (JWT 'api' guard): a token only works while its owner is
    // still current staff - see App\Support\MobileAppAccess.
    \Illuminate\Support\Facades\Auth::provider('current_staff', function ($app, array $config) {
      return (new \Illuminate\Auth\EloquentUserProvider($app['hash'], $config['model']))
        ->withQuery(fn ($query) => $query->whereIn('status', \App\Support\MobileAppAccess::STATUSES));
    });

    // Never mail an address that cannot be delivered to. Registered explicitly
    // rather than left to event discovery so it cannot quietly stop applying.
    \Illuminate\Support\Facades\Event::listen(
        \Illuminate\Notifications\Events\NotificationSending::class,
        \App\Listeners\SkipUnmailableRecipients::class
    );

    \Illuminate\Support\Facades\Gate::before(function ($user, $ability) {
        return $user->hasRole(['admin', 'Admin', 'super_admin']) ? true : null;
    });



    \Illuminate\Support\Facades\Gate::define('access-sales-ops', function ($user) {
        // Admins and HR see everything
        if ($user->hasRole(['admin', 'hr', 'Admin', 'HR'])) {
            return true;
        }
        // NOTE: cc_attendance.manage deliberately does NOT grant this gate.
        // It only opens the CC Attendance page, and the sidebar handles that
        // by letting the "Sales Field Ops" group itself accept either
        // permission while each sales sub-link stays gated on this one — so an
        // attendance-only delegate sees Attendance and nothing else.
        // Mapped salespeople / CC agents from the salesperson map
        if (\App\Models\CcSalespersonMap::where('cc_user_id', $user->id)
            ->orWhere('sales_user_id', $user->id)
            ->exists()) {
            return true;
        }
        // Allow users from sales-adjacent departments:
        // Sales Department (2), Customer Care (10), New Biz (13), Customer
        // Excellence (20), Pre Sales (21), Channel Sales (22), Sales (30),
        // Customer Care + New Biz (34), Export Department (14).
        //
        // 34 is the merged Customer Care + New Biz department. It was missing
        // here, so its members only reached Sales Field Ops if they happened to
        // have a row in cc_salesperson_map — 8 of the 9 did, which hid the gap
        // until the 9th (a manager, unmapped) could not see the menu at all.
        //
        // 14 (Export Department) is granted so the export team sees the Sales
        // Field Ops menu; their DATA stays self-scoped by SalesScope (export
        // is not a CCare/New Biz "rep" and not admin/hr, so a plain export user
        // sees only their own book — an export manager sees only their reports).
        $salesDeptIds = [2, 10, 13, 14, 20, 21, 22, 30, 34];
        return in_array($user->department_id, $salesDeptIds);
    });

    // ── Employee profile tab gate: KPIs ─────────────────────────────────
    // Pilot for per-tab granular access (see the 2026-09 granular-access
    // review). employee.kpa already existed in the permissions registry but
    // nothing ever checked it, so the KPIs tab rendered unconditionally for
    // anyone who could open the profile at all — unlike the Banking &
    // Payroll tab next to it, which already does exactly this same check
    // (see $canViewFinancials in employees/view.blade.php).
    //
    // Today, in practice, that population is exactly admin/hr/accounts (no
    // role currently holds employee.kpa directly, and route access is
    // role_or_permission:admin|hr|accounts|employee.view|...|employee.kpa|...) —
    // so the role check here is not a new grant, it is what already
    // determines who sees this tab today. Wiring the real permission on top,
    // rather than instead of, means nobody's current access changes.
    \Illuminate\Support\Facades\Gate::define('employees.tab.kpa', function ($user) {
        return $user->hasRole(['admin', 'hr', 'accounts', 'Admin', 'HR']) || $user->can('employee.kpa');
    });

    Paginator::useBootstrapFive();

    Vite::useStyleTagAttributes(function (?string $src, string $url, ?array $chunk, ?array $manifest) {
      if ($src !== null) {
        return [
          'class' => preg_match("/(resources\/assets\/vendor\/scss\/(rtl\/)?core)-?.*/i", $src) ? 'template-customizer-core-css' :
            (preg_match("/(resources\/assets\/vendor\/scss\/(rtl\/)?theme)-?.*/i", $src) ? 'template-customizer-theme-css' : '')
        ];
      }
      return [];
    });

    /**
     * Register Custom Migration Paths
     */
    $this->loadMigrationsFrom([
      database_path() . DIRECTORY_SEPARATOR . 'migrations' . DIRECTORY_SEPARATOR . 'tenant'
    ]);

    /**
     * Alias Constants and ModuleConstants for global access
     */
    $constantsPath = app_path('Constants/Constants.php');
    if (is_file($constantsPath)) {
      require_once $constantsPath;
    }
    if (class_exists(\App\Constants\Constants::class) && !class_exists('Constants')) {
      class_alias(\App\Constants\Constants::class, 'Constants');
    }

    $moduleConstantsPath = app_path('Constants/ModuleConstants.php');
    if (is_file($moduleConstantsPath)) {
      require_once $moduleConstantsPath;
    }
    if (class_exists(\App\Constants\ModuleConstants::class) && !class_exists('ModuleConstants')) {
      class_alias(\App\Constants\ModuleConstants::class, 'ModuleConstants');
    }

    /**
     * Ensure $settings is NEVER null for any view (Final defense)
     * And provide global probation alerts for managers
     */
    \Illuminate\Support\Facades\View::composer('*', function ($view) {
      // 1. App Settings
      if (!isset($view->settings) || is_null($view->settings)) {
          $s = \App\Models\Settings::first();
          if (!$s) {
              $s = new \App\Models\Settings();
              $s->app_name = config('app.name', 'HRX');
              $s->currency_symbol = '₹';
              $s->currency = 'INR';
              $s->available_modules = json_encode([]);
          }
          $view->with('settings', $s);
      }

      // 2. Global Probation Alerts for Managers
      if (auth()->check()) {
          $user = auth()->user();

          // 2a. Delegate Access hub (top navbar): while inside a delegated
          // account show the exit control; otherwise list the vacant-position
          // accounts this user may log in as.
          $switchOriginalId = session('delegate_switch_original_id');
          if ($switchOriginalId) {
              $originalUser = \App\Models\User::find($switchOriginalId);
              $view->with('delegateHub', [
                  'mode'         => 'acting',
                  'actingAs'     => $user->getFullName(),
                  'originalName' => $originalUser ? $originalUser->getFullName() : 'My Account',
              ]);
          } else {
              $delegatedAccounts = $user->delegatedAccounts()
                  ->where('status', \App\Enums\UserAccountStatus::ACTIVE)
                  ->get(['id', 'first_name', 'last_name']);
              if ($delegatedAccounts->isNotEmpty()) {
                  $view->with('delegateHub', [
                      'mode'     => 'available',
                      'accounts' => $delegatedAccounts,
                  ]);
              }
          }

          // Check if user has manager-like responsibilities
          if ($user->hasRole(['admin', 'Admin', 'hr', 'manager', 'HR', 'Manager'])) {
              $pendingProbations = \App\Models\User::where('reporting_to_id', $user->id)
                  ->where('status', \App\Enums\UserAccountStatus::ACTIVE)
                  ->whereNotNull('probation_end_date')
                  ->whereNull('probation_confirmed_at')
                  ->whereDate('probation_end_date', '<=', now()->toDateString())
                  ->whereDoesntHave('probationEvaluations', function($q) {
                      $q->where('hr_status', 'pending');
                  })
                  ->with('designation')
                  ->get();
              
              if ($pendingProbations->isNotEmpty()) {
                  $view->with('globalPendingProbations', $pendingProbations);
              }
          }

          // 3. Pending Mandatory HR Policies (For ACTIVE Users Only)
          if ($user->status === \App\Enums\UserAccountStatus::ACTIVE) {
            $pendingPolicies = \App\Models\HRPolicy::where('is_active', true)
                ->where('show_as_popup', true)
                ->whereDoesntHave('acknowledgments', function($q) use ($user) {
                    $q->where('user_id', $user->id);
                })
                ->get()
                ->filter(function ($policy) use ($user) {
                    if (empty($policy->target_departments)) {
                        return true;
                    }
                    return in_array((string)$user->department_id, $policy->target_departments);
                });
            
            if ($pendingPolicies->isNotEmpty()) {
                $view->with('globalPendingPolicies', $pendingPolicies);
            }
          }
      }
    });
  }
}
