<?php

namespace App\Notifications\Chat;

use App\Channels\FirebaseChannel;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class NewChatMessage extends Notification
{
  use Queueable;

  private string $title;

  private string $message;

  /**
   * Create a new notification instance.
   */
  public function __construct(string $title, string $message)
  {
    $this->title = $title;
    $this->message = $message;
  }

  /**
   * Get the notification's delivery channels.
   *
   * @return array<int, string>
   */
  public function via(object $notifiable): array
  {
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

  /**
   * Get the array representation of the notification.
   *
   * @return array<string, mixed>
   */
  public function toArray(object $notifiable): array
  {
    return [
      //
    ];
  }

  public function toDatabase($notifiable): array
  {
    return [
      'title' => $this->title,
      'message' => $this->message
    ];
  }

  public function toFirebase()
  {
    return [
      'title' => $this->title,
      'body' => $this->message
    ];
  }
}
