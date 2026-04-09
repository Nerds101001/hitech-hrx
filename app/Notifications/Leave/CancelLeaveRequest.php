<?php

namespace App\Notifications\Leave;

use App\Channels\FirebaseChannel;
use App\Models\LeaveRequest;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class CancelLeaveRequest extends Notification
{
  use Queueable;

  private LeaveRequest $request;
  private string $title;
  private string $message;

  /**
   * Create a new notification instance.
   */
  public function __construct(LeaveRequest $request)
  {
    $this->request = $request;
    $this->title = 'Leave Request Cancelled';
    $this->message = 'Leave request has been cancelled by ' . $request->user->getFullName();
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
   * Get the array representation of the notification.
   *
   * @return array<string, mixed>
   */
  public function toArray(object $notifiable): array
  {
    return [
      'title' => $this->title,
      'message' => $this->message,
      'request' => $this->request
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
