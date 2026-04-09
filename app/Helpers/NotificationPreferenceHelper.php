<?php

namespace App\Helpers;

use App\Enums\NotificationPreferenceType;
use App\Models\NotificationPreference;
use App\Models\User;
use Illuminate\Support\Facades\Notification;

class NotificationPreferenceHelper
{
  /**
   * Get a user's notification preference for a specific type.
   *
   * @param User $user
   * @param NotificationPreferenceType $type
   * @param bool $default
   * @return bool
   */
  public static function getPreference(User $user, NotificationPreferenceType $type, bool $default = true): bool
  {
    $preferences = $user->notificationPreference?->preferences ?? [];
    return $preferences[$type->value] ?? $default;
  }

  /**
   * Set a user's notification preference for a specific type.
   *
   * @param User $user
   * @param NotificationPreferenceType $type
   * @param bool $value
   * @return void
   */
  public static function setPreference(User $user, NotificationPreferenceType $type, bool $value): void
  {
    $preference = $user->notificationPreference ?? new NotificationPreference(['user_id' => $user->id]);

    $preferences = $preference->preferences ?? [];
    $preferences[$type->value] = $value;

    $preference->preferences = $preferences;
    $preference->save();
  }

  /**
   * Notify users who have enabled a specific notification preference.
   *
   * @param NotificationPreferenceType $type
   * @param mixed $notification
   * @return void
   */
  public static function notifyUsers(NotificationPreferenceType $type, $notification): void
  {
    $users = User::with('notificationPreference')
      ->whereHas('notificationPreference', function ($query) use ($type) {
        $query->whereJsonContains('preferences->' . $type->value, true);
      })
      ->get();

    Notification::send($users, $notification);
  }

  /**
   * Get all notification preferences for display.
   *
   * @param User $user
   * @return array
   */
  public static function getAllPreferences(User $user): array
  {
    $preferences = $user->notificationPreference?->preferences ?? [];
    $result = [];

    foreach (NotificationPreferenceType::all() as $type) {
      $result[$type->value] = $preferences[$type->value] ?? true; // Default to true if not set
    }

    return $result;
  }
}
