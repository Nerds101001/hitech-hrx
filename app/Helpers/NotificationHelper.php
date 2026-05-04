<?php

namespace App\Helpers;

use App\Models\User;
use Exception;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;

class NotificationHelper
{
  public static function notifyAdminHR($notification, $isExceptMe = true): void
  {
    try {
      $authUser = auth()->user();

      // Retrieve HR admins, HR managers, Admin
      $hrAdmins = User::whereHas('roles', function ($query) {
        $query->whereIn('name', ['hr', 'admin']);
      })->where('status', \App\Enums\UserAccountStatus::ACTIVE)->get();

      $reportingTo = $authUser->reportingTo;

      // Prepare list of users to notify: HR/Admins + Direct Manager
      $notifiables = $hrAdmins;
      if ($reportingTo) {
          $notifiables = $notifiables->push($reportingTo);
      }
      
      $notifiables = $notifiables->filter()->unique('id');

      if ($isExceptMe) {
        $notifiables = $notifiables->where('id', '!=', $authUser->id);
      }

      // Send the notification
      Notification::send($notifiables, $notification);
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

    $reportingTo = $authUser->reportingTo;

    // Prepare list of users to notify
    $notifiables = !$reportingTo ? $admins->merge([auth()->user()])->filter() : $admins->merge([$reportingTo, auth()->user()])->filter();

    if ($isExceptMe) {
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

    $reportingTo = $authUser->reportingTo;

    // Prepare list of users to notify
    $notifiables = !$reportingTo ? $managers->merge([auth()->user()])->filter() : $managers->merge([$reportingTo, auth()->user()])->filter();

    if ($isExceptMe) {
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

    $reportingTo = $authUser->reportingTo;

    // Prepare list of users to notify
    $notifiables = !$reportingTo ? $hrAdmins->merge([auth()->user()])->filter() : $hrAdmins->merge([$reportingTo, auth()->user()])->filter();

    if ($isExceptMe) {
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

}
