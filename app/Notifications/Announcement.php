<?php

namespace App\Notifications;

use App\Channels\FirebaseChannel;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Log;

class Announcement extends Notification
{
  use Queueable;

  public string $message;

  /**
   * Create a new notification instance.
   */
  public function __construct(string $message)
  {
    $this->message = $message;
  }

  /**
   * Get the notification's delivery channels.
   *
   * @return array<int, string>
   */
  public function via(object $notifiable): array
  {
    // return ['mail'];
    return ['database', FirebaseChannel::class];
  }

  /**
   * Get the mail representation of the notification.
   */
  public function toMail(object $notifiable): MailMessage
  {
    return (new MailMessage)
      ->line('The introduction to the notification.')
      ->action('Notification Action', url('/'))
      ->line('Thank you for using our application!');
  }

  public function toDatabase($notifiable): array
  {
    Log::info('Database is triggered');
    return [
      'user_id' => $notifiable->id,
      'message' => $this->message,
    ];
  }

  /**
   * Get the array representation of the notification.
   *
   * @return array<string, mixed>
   */
  public function toArray(object $notifiable): array
  {
    return [
      'data' => 'Demo announcement of : ' . $this->message,
    ];
  }

  public function toFirebase($notifiable)
  {
    return [
      'title' => 'Test Notification',
      'body' => $this->message
    ];
  }

}
