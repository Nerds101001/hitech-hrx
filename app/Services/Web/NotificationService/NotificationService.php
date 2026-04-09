<?php

namespace App\Services\Web\NotificationService;


use App\ApiClasses\Error;
use App\ApiClasses\Success;
use App\Models\Notification;
use App\Models\User;
use App\Models\UserDevice;
use App\Models\UserNotification;
use Exception;
use Illuminate\Http\Request;

class NotificationService implements INotificationService
{
  public function getAllNotifications()
  {
    return Notification::where('user_id', auth()->user()->id)
      ->with('user')
      ->orderBy('created_at', 'desc')
      ->get();
  }

  public function getUserNotifications()
  {
    return UserNotification::where('user_id', auth()->user()->id)
      ->with('notification')
      ->orderBy('created_at', 'desc')
      ->get();
  }

  public function markAllAsRead()
  {
    $userNotifications = UserNotification::where('user_id', auth()->user()->id)
      ->where('is_read', false)
      ->get();

    foreach ($userNotifications as $userNotification) {
      $userNotification->is_read = true;
      $userNotification->read_at = now();
      $userNotification->save();
    }

    return Success::response('All notifications marked as read');
  }

  public function createNotification(Request $request)
  {
    $title = $request->title;
    $message = $request->message;
    $notificationFor = $request->notificationFor;
    $user = $request->user;
    $role = $request->role;

    if (empty($title) || empty($message)) {
      return Error::response('Title and message are required');
    }

    if ($notificationFor == 'user' && empty($user)) {
      return Error::response('User is required');
    }

    if ($notificationFor == 'role' && empty($role)) {
      return Error::response('Role is required');
    }

    try {
      $notification = new Notification();
      $notification->title = $title;
      $notification->message = $message;
      $notification->user_id = auth()->user()->id;
      $notification->created_by_id = auth()->user()->id;
      $notification->type = $notificationFor == 'user' ? 'user' : ($notificationFor == 'role' ? 'role' : 'all');
      $notification->save();

      $this->createUserNotifications($notification, $notificationFor, $user, $role);

      return Success::response('Notification created successfully');
    } catch (Exception $e) {
      return Error::response('An error occurred while creating notification: ' . $e->getMessage());
    }
  }

  private function createUserNotifications($notification, $notificationFor, $user, $role)
  {
    if ($notificationFor == 'user') {
      $this->createUserNotification($notification->id, $user);
    } elseif ($notificationFor == 'role') {
      $userIds = User::with('roles')->whereHas('roles', function ($query) use ($role) {
        $query->where('name', $role)
          ->select('id');
      })->get();

      foreach ($userIds as $userId) {
        $this->createUserNotification($notification->id, $userId->id);
      }
    } else {
      $users = User::where('id', '!=', auth()->user()->id)->select('id')->get();

      foreach ($users as $user) {
        $this->createUserNotification($notification->id, $user->id);
      }
    }
  }

  private function createUserNotification($notificationId, $userId)
  {
    $userNotification = new UserNotification();
    $userNotification->notification_id = $notificationId;
    $userNotification->user_id = $userId;
    $userNotification->save();
  }

  public function markAsRead($id)
  {
    $userNotification = UserNotification::where('id', $id)
      ->where('user_id', auth()->user()->id)
      ->first();

    if (!$userNotification) {
      return Error::response('Notification not found');
    }

    $userNotification->is_read = true;
    $userNotification->read_at = now();
    $userNotification->save();

    return Success::response('Notification marked as read');
  }

  public function deleteNotification($id)
  {
    try {
      $notification = Notification::find($id);
      if (!$notification) {
        return Error::response('Notification not found');
      }

      $notification->delete();

      return Success::response('Notification deleted successfully');
    } catch (Exception $e) {
      return Error::response('An error occurred while deleting the notification: ' . $e->getMessage());
    }
  }

  public function saveToken(Request $request)
  {
    $token = $request->token;
    try {
      $user = auth()->user();

      $userDevice = UserDevice::where('user_id', $user->id)
        ->where('push_token', $token)
        ->first();

      if (!$userDevice) {
        $userDevice = new UserDevice();
        $userDevice->device_id = $this->createDeviceFingerprint();
        $userDevice->user_id = $user->id;
        $userDevice->push_token = $token;
        $userDevice->save();
      } else {
        $userDevice->push_token = $token;
        $userDevice->save();
      }

      return Success::response('Token saved successfully');
    } catch (Exception $e) {
      return Error::response('An error occurred while saving token: ' . $e->getMessage());
    }
  }

  private function createDeviceFingerprint()
  {
    $userAgent = $_SERVER['HTTP_USER_AGENT'];
    $acceptLanguage = $_SERVER['HTTP_ACCEPT_LANGUAGE'];
    $ipAddress = $_SERVER['REMOTE_ADDR'];

    $fingerprint = $userAgent . $acceptLanguage . $ipAddress;

    return md5($fingerprint);
  }
}

