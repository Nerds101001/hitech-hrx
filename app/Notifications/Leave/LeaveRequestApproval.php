<?php

namespace App\Notifications\Leave;

use App\Channels\FirebaseChannel;
use App\Models\LeaveRequest;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class LeaveRequestApproval extends Notification
{
  use Queueable;

  private LeaveRequest $leaveRequest;

  private string $status;
  private string $title;
  private string $message;

  /**
   * Create a new notification instance.
   */
  public function __construct(LeaveRequest $request, $status)
  {
    $this->leaveRequest = $request;
    $this->status = $status;
    $this->title = 'Leave Request Approval';
    $this->message = 'Your leave request has been ' . $status;
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
      //
    ];
  }

  public function toDatabase($notifiable): array
  {
    return [
      'title' => $this->title,
      'message' => $this->message,
      'request' => $this->leaveRequest,
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
