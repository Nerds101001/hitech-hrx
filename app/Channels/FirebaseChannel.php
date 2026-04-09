<?php

namespace App\Channels;

use Exception;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Log;
use Kreait\Firebase\Messaging\CloudMessage;
use Kreait\Laravel\Firebase\Facades\Firebase;

class FirebaseChannel
{
  /**
   * Send the given notification.
   *
   * @param mixed $notifiable
   * @param Notification $notification
   * @return void
   */
  public function send($notifiable, Notification $notification)
  {
    // Check if the notification has a toFirebase method
    if (!method_exists($notification, 'toFirebase')) {
      Log::error('Notification is missing toFirebase method.');
      return;
    }

    // Get the Firebase message payload
    $messageData = $notification->toFirebase($notifiable);

    if (empty($messageData['title']) || empty($messageData['body'])) {
      Log::error('Firebase notification data is incomplete.', $messageData);
      return;
    }

    $token = $notifiable->fcmToken();

    if (empty($token)) {
      Log::warning("No FCM token found for user ID {$notifiable->id}");
      return;
    }
    try {
      $message = CloudMessage::fromArray([
        'token' => $token,
        'notification' => [
          'title' => $messageData['title'],
          'body' => $messageData['body'],
        ],
      ]);

      Firebase::messaging()->send($message);
      Log::info("Firebase notification sent to user ID {$notifiable->id}");
    } catch (Exception $e) {
      Log::error("Failed to send Firebase notification: " . $e->getMessage());
    }
  }
}

