<?php

namespace App\Notifications\Leave;

use App\Channels\FirebaseChannel;
use App\Models\LeaveRequest;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use App\Models\User;

class NewLeaveRequest extends Notification
{
  use Queueable;

  private LeaveRequest $leaveRequest;

  private string $title;
  private string $message;

  /**
   * Create a new notification instance.
   */
  public function __construct(LeaveRequest $leaveRequest)
  {
    $this->leaveRequest = $leaveRequest;
    $this->title = 'New Leave Request';
    $this->message = 'You have a new leave request from ' . $leaveRequest->user->getFullName();
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
    $employee = $this->leaveRequest->user;
    $leaveType = $this->leaveRequest->leaveType->name ?? 'Leave';
    $fromDate = $this->leaveRequest->from_date->format('d M, Y');
    $toDate = $this->leaveRequest->to_date->format('d M, Y');
    $duration = $this->leaveRequest->from_date->diffInDays($this->leaveRequest->to_date) + 1;

    $hrEmails = User::role('hr')->pluck('email')->toArray();
    $isRecipientHR = $notifiable->hasRole('hr');

    $mail = (new MailMessage)
      ->subject('New Leave Application: ' . $employee->getFullName())
      ->view('emails.new_leave_request', [
        'notifiable' => $notifiable,
        'employeeName' => $employee->getFullName(),
        'employeeCode' => $employee->code,
        'leaveType' => $leaveType,
        'fromDate' => $fromDate,
        'toDate' => $toDate,
        'duration' => $duration,
        'userNotes' => $this->leaveRequest->user_notes,
        'isBackdated' => $this->leaveRequest->from_date->lt($this->leaveRequest->created_at->startOfDay())
      ]);

    if (!$isRecipientHR && !empty($hrEmails)) {
        $mail->cc($hrEmails);
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
      'request' => $this->leaveRequest
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
