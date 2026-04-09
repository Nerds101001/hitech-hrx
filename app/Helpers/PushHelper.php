<?php

namespace App\Helpers;

use App\Models\Notification;
use App\Models\User;
use App\Models\UserDevice;
use Illuminate\Support\Facades\Log;
use Kreait\Firebase\Messaging\CloudMessage;
use Kreait\Laravel\Firebase\Facades\Firebase;

class PushHelper
{
  protected $notification;

  public function __construct()
  {
    $this->notification = Firebase::messaging();
  }

  function test($data)
  {
    $userDevices = UserDevice::all();

    foreach ($userDevices as $userDevice) {
      $message = CloudMessage::fromArray([
        'token' => $userDevice->token,
        'notification' => [
          'title' => 'Test Notification',
          'body' => $data
        ],
      ]);

      $this->notification->send($message);
    }
  }

  function sendNotificationToUser($userId, $title, $message)
  {
    try {
      Notification::create([
        'from_user_id' => $userId,
        'title' => $title,
        'description' => $message,
        'type' => 'user',
        'created_by_id' => $userId
      ]);

      $userToken = UserDevice::where('user_id', $userId)->first();
      if ($userToken) {
        $message = CloudMessage::fromArray([
          'token' => $userToken->token,
          'notification' => [
            'title' => $title,
            'body' => $message
          ],
        ]);

        $this->notification->send($message);
      }
    } catch (\Exception $e) {
      Log::error($e->getMessage());
    }
  }

  function sendNotificationToAdmin($title, $message)
  {
    try {
      Notification::create([
        'title' => $title,
        'description' => $message,
        'type' => 'admin',
        'created_by_id' => 1
      ]);

      $adminUsers = User::where('shift_id', '==', null)->get();

      foreach ($adminUsers as $adminUser) {
        $userToken = UserDevice::where('user_id', $adminUser->id)->first();
        if ($userToken) {
          $message = CloudMessage::fromArray([
            'token' => $userToken->token,
            'notification' => [
              'title' => $title,
              'body' => $message
            ],
          ]);

          $this->notification->send($message);
        }
      }
    } catch (\Exception $e) {
      Log::error($e->getMessage());
    }
  }

  function sendNotificationForChat($fromUserId, $toUserId, $message)
  {
    try {
      $fromUser = User::where('id', $fromUserId)->with('userDevice')->first();
      $toUser = User::where('id', $toUserId)->with('userDevice')->first();

      $title = 'New Message from ' . $fromUser->first_name . ' ' . $fromUser->last_name;

      Notification::create([
        'from_user_id' => $fromUserId,
        'to_user_id' => $toUserId,
        'title' => $title,
        'description' => $message,
        'type' => 'chat',
        'created_by_id' => $fromUserId
      ]);

      if ($toUser) {
        $message = CloudMessage::fromArray([
          'token' => $toUser->userDevice->token,
          'notification' => [
            'title' => $title,
            'body' => $message
          ],
        ]);

        $this->notification->send($message);
      }
    } catch (\Exception $e) {
      Log::error($e->getMessage());
    }
  }

  function sendNotificationForTeamChat($teamId, $fromUserId, $message, $isExceptUserId = false): void
  {
    try {
      $fromUser = User::where('id', $fromUserId)->with('userDevice')->first();
      $title = 'New Message from ' . $fromUser->getFullName();

      Notification::create([
        'from_user_id' => $fromUserId,
        'title' => $title,
        'description' => $message,
        'type' => 'chat',
        'created_by_id' => $fromUserId
      ]);

      if ($isExceptUserId) {
        $this->sendNotificationToTeamExceptUserId($teamId, $fromUserId, $title, $message);

      } else {
        $this->sendNotificationToTeam($teamId, $title, $message);
      }
    } catch (\Exception $e) {
      Log::error($e->getMessage());
    }

  }

  function sendNotificationToTeam($teamId, $title, $message)
  {
    try {
      $tokens = UserDevice::whereHas('user', function ($query) use ($teamId) {
        $query->where('team_id', $teamId);
      })->pluck('token')->toArray();

      $message = CloudMessage::fromArray([
        'tokens' => $tokens,
        'notification' => [
          'title' => $title,
          'body' => $message
        ],
      ]);

      $this->notification->sendMulticast($message, $tokens);
    } catch (\Exception $e) {
      Log::error($e->getMessage());
    }
  }

  function sendNotificationToTeamExceptUserId($teamId, $userId, $title, $message)
  {
    try {
      $tokens = UserDevice::whereHas('user', function ($query) use ($teamId, $userId) {
        $query->where('team_id', $teamId)->where('id', '!=', $userId);
      })->pluck('token')->toArray();

      $message = CloudMessage::fromArray([
        'tokens' => $tokens,
        'notification' => [
          'title' => $title,
          'body' => $message
        ],
      ]);

      $this->notification->sendMulticast($message, $tokens);
    } catch (\Exception $e) {
      Log::error($e->getMessage());
    }
  }

  function sendNotificationToAll($title, $message)
  {
    try {
      $tokens = UserDevice::all()->pluck('token')->toArray();
      $message = CloudMessage::fromArray([
        'tokens' => $tokens,
        'notification' => [
          'title' => $title,
          'body' => $message
        ],
      ]);

      $tokens = UserDevice::all()->pluck('token')->toArray();

      $message = CloudMessage::fromArray([
        'notification' => [
          'title' => $title,
          'body' => $message
        ],
      ]);

      $this->notification->sendMulticast($message, $tokens);
    } catch (\Exception $e) {
      Log::error($e->getMessage());
    }
  }

}
