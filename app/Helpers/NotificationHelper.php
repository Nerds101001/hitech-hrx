<?php

namespace App\Helpers;

use App\Models\User;
use Exception;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;

class NotificationHelper
{
  /**
   * A manager plus, when that manager is a vacant-position placeholder
   * account, the real employee its access is delegated to — so requests
   * routed to an unmanned account still reach a human.
   *
   * @return array<User>
   */
  private static function withDelegate(?User $manager): array
  {
    if (!$manager) {
      return [];
    }
    $out = [$manager];
    if ($manager->access_delegate_id && $manager->accessDelegate) {
      $out[] = $manager->accessDelegate;
    }
    return $out;
  }

  public static function notifyAdminHR($notification, $isExceptMe = true): void
  {
    try {
      $authUser = auth()->user();

      // Retrieve HR staff only (Admins are excluded unless they are the direct manager)
      $hrStaff = User::whereHas('roles', function ($query) {
        $query->where('name', 'hr');
      })->where('status', \App\Enums\UserAccountStatus::ACTIVE)->get();

      $reportingTo = $authUser ? $authUser->reportingTo : null;

      // Prepare list of users to notify: HR Staff + Direct Manager (+ the
      // manager's acting delegate when the position is vacant)
      $notifiables = $hrStaff;
      foreach (self::withDelegate($reportingTo) as $m) {
          $notifiables = $notifiables->push($m);
      }
      
      // Dedupe by who actually receives mail (User::routeNotificationForMail
      // redirects a vacant-position account to its delegate's inbox), not by
      // raw id — otherwise the manager entry and the delegate entry pushed
      // above by withDelegate() both resolve to the same person and that
      // person gets the same email twice.
      $notifiables = $notifiables->filter()->unique(fn ($u) => $u->effectiveActor()->id);

      if ($isExceptMe && $authUser) {
        $notifiables = $notifiables->where('id', '!=', $authUser->id);
      }

      // One send per recipient. Notification::send() mails a whole collection in
      // a single loop, so a hard bounce — an ex-employee whose address was
      // deliberately mangled on exit, say — threw and everybody queued behind
      // them silently got nothing. The old catch below logged one line and the
      // real manager and HR never learned a request existed.
      foreach ($notifiables as $notifiable) {
        try {
          Notification::send([$notifiable], $notification);
        } catch (Exception $e) {
          Log::error('Notification failed for user ' . ($notifiable->id ?? '?')
            . ' (' . ($notifiable->email ?? 'no address') . '): ' . $e->getMessage());
        }
      }
    } catch (Exception $e) {
      Log::error($e->getMessage());
    }
  }

  public static function notifyAdmin($notification, $isExceptMe = true): void
  {
    $authUser = auth()->user();

    // Retrieve Admin and the user's reporting manager
    $admins = User::with('roles')->whereHas('roles', function ($query) {
      $query->where('name', 'admin');
    })->get();

    $reportingTo = $authUser ? $authUser->reportingTo : null;

    // Prepare list of users to notify
    $managerSet = self::withDelegate($reportingTo);
    $notifiables = empty($managerSet) ? $admins->merge([auth()->user()])->filter() : $admins->merge(array_merge($managerSet, [auth()->user()]))->filter();

    if ($isExceptMe && $authUser) {
      $notifiables = $notifiables->where('id', '!=', $authUser->id);
    }

    // Send the notification
    Notification::send($notifiables, $notification);
  }

  public static function notifyManager($notification, $isExceptMe = true): void
  {
    $authUser = auth()->user();

    // Retrieve Admin and the user's reporting manager
    $managers = User::with('roles')->whereHas('roles', function ($query) {
      $query->where('name', 'manager');
    })->get();

    $reportingTo = $authUser ? $authUser->reportingTo : null;

    // Prepare list of users to notify
    $managerSet = self::withDelegate($reportingTo);
    $notifiables = empty($managerSet) ? $managers->merge([auth()->user()])->filter() : $managers->merge(array_merge($managerSet, [auth()->user()]))->filter();

    if ($isExceptMe && $authUser) {
      $notifiables = $notifiables->where('id', '!=', $authUser->id);
    }

    // Send the notification
    Notification::send($notifiables, $notification);
  }

  public static function notifyHR($notification, $isExceptMe = true): void
  {
    $authUser = auth()->user();

    // Retrieve HR admins, HR managers, Admin and the user's reporting manager
    $hrAdmins = User::with('roles')->whereHas('roles', function ($query) {
      $query->where('name', 'hr');
    })->get();

    $reportingTo = $authUser ? $authUser->reportingTo : null;

    // Prepare list of users to notify
    $managerSet = self::withDelegate($reportingTo);
    $notifiables = empty($managerSet) ? $hrAdmins->merge([auth()->user()])->filter() : $hrAdmins->merge(array_merge($managerSet, [auth()->user()]))->filter();

    if ($isExceptMe && $authUser) {
      $notifiables = $notifiables->where('id', '!=', $authUser->id);
    }

    // Send the notification
    Notification::send($notifiables, $notification);
  }

  public static function notifyAllExceptMe($notification): void
  {
    $authUser = auth()->user();

    // Retrieve all users except the authenticated user
    $users = User::where('id', '!=', $authUser->id)->get();

    // Send the notification
    Notification::send($users, $notification);
  }

  public static function notifySuperAdmins($notification): void
  {
    // Retrieve all super admins
    $superAdmins = User::whereHas('roles', function ($query) {
      $query->where('name', 'super_admin');
    })->get();

    // Send the notification
    Notification::send($superAdmins, $notification);
  }

  public static function notifyTenants($notification): void
  {
    // Retrieve all tenants
    $tenants = User::whereHas('roles', function ($query) {
      $query->where('name', 'customer');
    })->get();

    // Send the notification
    Notification::send($tenants, $notification);
  }

  public static function notifySuperAdminsAndTenants($notification): void
  {
    // Retrieve all super admins and tenants
    $users = User::whereHas('roles', function ($query) {
      $query->where('name', 'super_admin')->orWhere('name', 'customer');
    })->get();

    // Send the notification
    Notification::send($users, $notification);
  }

  /**
   * Notifies only HR and Admin roles, specifically excluding any reporting managers.
   * Useful for sensitive financial requests like Expenses.
   */
  public static function notifyHRAndAdminOnly($notification, $isExceptMe = true): void
  {
    try {
      $authUser = auth()->user();

      $recipients = User::whereHas('roles', function ($query) {
        $query->whereIn('name', ['hr', 'admin']);
      })->where('status', \App\Enums\UserAccountStatus::ACTIVE)->get();

      if ($isExceptMe && $authUser) {
        $recipients = $recipients->where('id', '!=', $authUser->id);
      }

      Notification::send($recipients, $notification);
    } catch (Exception $e) {
      Log::error($e->getMessage());
    }
  }

}
