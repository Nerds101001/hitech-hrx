<?php

namespace App\Notifications\Expense;

use App\Channels\FirebaseChannel;
use App\Models\ExpenseRequest;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class ExpenseRequestApproval extends Notification
{
  use Queueable;

  private ExpenseRequest $request;

  private string $status;
  private string $title;
  private string $message;

  /**
   * Create a new notification instance.
   */
  public function __construct(ExpenseRequest $request, string $status)
  {
    $this->request = $request;
    $this->status = $status;
    $this->title = 'Expense Request Approval';
    $this->message = 'Your expense request has been ' . $status;
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
      'message' => $this->message,
      'request' => $this->request,
    ];
  }

  public function toFirebase($notifiable)
  {
    return [
      'title' => $this->title,
      'body' => $this->message,
    ];
  }
}
