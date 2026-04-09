<?php

namespace App\Notifications\SuperAdmin\Order;

use App\Models\SuperAdmin\OfflineRequest;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class OfflineOrderRequestCancellationSa extends Notification
{
  use Queueable;

  private OfflineRequest $request;

  /**
   * Create a new notification instance.
   */
  public function __construct(OfflineRequest $request)
  {
    $this->request = $request;
  }

  /**
   * Get the notification's delivery channels.
   *
   * @return array<int, string>
   */
  public function via(object $notifiable): array
  {
    return ['mail'];
  }

  /**
   * Get the mail representation of the notification.
   */
  public function toMail(object $notifiable): MailMessage
  {
    return (new MailMessage)
      ->subject('Offline Order Request Cancelled')
      ->greeting('Hello,')
      ->line('An offline order request has been cancelled.')
      ->line('Here are the details of the cancelled request:')
      ->line('**User:** ' . $this->request->user->getFullName())
      ->line('**Plan:** ' . $this->request->plan->name)
      ->line('**Type:** ' . ucfirst(str_replace('_', ' ', $this->request->type->value)))
      ->line('**Amount:** ' . number_format($this->request->amount, 2))
      ->line('**Cancellation Reason:** ' . ($this->request->cancelled_reason ?? 'N/A'))
      ->line('**Notes:** ' . ($this->request->notes ?? 'N/A'))
      ->action('View Requests', route('offlineRequests.index'))
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
      'user' => $this->request->user->getFullName(),
      'plan' => $this->request->plan->name,
      'type' => $this->request->type->value,
      'amount' => $this->request->amount,
      'cancelled_reason' => $this->request->cancelled_reason,
    ];
  }
}
