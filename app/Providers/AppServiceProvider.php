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
    }
  }

  /**
   * Bootstrap any application services.
   */
  public function boot(): void
  {
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
