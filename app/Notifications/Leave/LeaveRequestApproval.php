<?php

namespace App\Notifications\Leave;

use App\Channels\FirebaseChannel;
use App\Models\LeaveRequest;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use App\Models\User;

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
    return ['database', 'mail', FirebaseChannel::class];
  }

  /**
   * Get the mail representation of the notification.
   */
  public function toMail(object $notifiable): MailMessage
  {
    $leaveType = $this->leaveRequest->leaveType->name ?? 'Leave';
    $fromDate = $this->leaveRequest->from_date->format('d M, Y');
    $toDate = $this->leaveRequest->to_date->format('d M, Y');
    $duration = $this->leaveRequest->from_date->diffInDays($this->leaveRequest->to_date) + 1;
    $statusText = ucfirst($this->status);

    $hrEmails = User::role('hr')->pluck('email')->toArray();
    $manager = $this->leaveRequest->user->reportingTo;
    $managerEmail = $manager?->email;
    
    $isRecipientHR = $notifiable->hasRole('hr');
    $isRecipientManager = ($manager && $notifiable->id === $manager->id);

    $mail = (new MailMessage)
      ->subject('Leave Request ' . $statusText . ': ' . $this->leaveRequest->user->getFullName())
      ->greeting('Hello ' . $notifiable->first_name . ',')
      ->line('Your leave request for ' . $leaveType . ' (' . $fromDate . ' to ' . $toDate . ') has been **' . $this->status . '**.')
      ->line('**Request Details:**')
      ->line('• Type: ' . $leaveType)
      ->line('• Duration: ' . $duration . ' days')
      ->line('• Dates: ' . $fromDate . ' - ' . $toDate)
      ->line('• Admin Notes: ' . ($this->leaveRequest->approval_notes ?: 'N/A'))
      ->action('View My Leaves', url('/user/leaves'))
      ->line('Regards,')
      ->line('HR Operations');

    $cc = [];
    if (!$isRecipientHR) {
        $cc = array_merge($cc, $hrEmails);
    }
    if (!$isRecipientManager && $managerEmail) {
        $cc[] = $managerEmail;
    }

    if (!empty($cc)) {
        $mail->cc(array_unique($cc));
    }

    return $mail;
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
