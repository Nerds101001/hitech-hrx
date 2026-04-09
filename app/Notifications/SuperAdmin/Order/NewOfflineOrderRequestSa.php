<?php

namespace App\Notifications\SuperAdmin\Order;

use App\Enums\OrderType;
use App\Models\SuperAdmin\OfflineRequest;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class NewOfflineOrderRequestSa extends Notification
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

    $subject = 'New Offline Order';
    if ($this->request->type == OrderType::PLAN) {
      $subject = 'New Offline Plan Order';
    } else if ($this->request->type == OrderType::RENEWAL) {
      $subject = 'Plan Offline Renewal Order';
    } else if ($this->request->type == OrderType::UPGRADE) {
      $subject = 'Plan Offline Upgrade Order';
    } else if ($this->request->type == OrderType::DOWNGRADE) {
      $subject = 'Plan Offline Downgrade Order';
    } else if ($this->request->type == OrderType::ADDITIONAL_USER) {
      $subject = 'Offline Additional User Order';
    } else {
      $subject = 'New Offline Order Request';
    }

    return (new MailMessage)
      ->subject($subject)
      ->greeting('Hello,')
      ->line('A new offline order request has been submitted.')
      ->line('Here are the details of the request:')
      ->line('**User:** ' . $this->request->user->getFullName())
      ->line('**Plan:** ' . $this->request->plan->name)
      ->line('**Type:** ' . ucfirst(str_replace('_', ' ', $this->request->type->value)))
      ->line('**Additional Users:** ' . $this->request->additional_users)
      ->line('**Per User Price:** ' . number_format($this->request->per_user_price, 2))
      ->line('**Amount:** ' . number_format($this->request->amount, 2))
      ->line('**Discount:** ' . ($this->request->discount_amount ? number_format($this->request->discount_amount, 2) : 'N/A'))
      ->line('**Total Amount:** ' . number_format($this->request->total_amount, 2))
      ->line('**Status:** ' . ucfirst($this->request->status->value))
      ->when($this->request->notes, function (MailMessage $mail) {
        $mail->line('**Notes:** ' . $this->request->notes);
      })
      ->action('View Request', route('offlineRequests.index'))
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
}
