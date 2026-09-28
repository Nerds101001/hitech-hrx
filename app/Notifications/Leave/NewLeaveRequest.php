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
  use Queueable, RespectsLeaveMute;

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

  public function via(object $notifiable): array
  {
    $employee = $this->leaveRequest->user;
    $reportingToId = $employee ? $employee->reporting_to_id : null;

    // Someone who has switched the leave broadcast off gets nothing -- unless
    // this request is waiting on them as the employee's manager.
    if (!$this->isApproverOf($notifiable, $employee) && $this->leaveMutedFor($notifiable)) {
        return [];
    }

    if ($reportingToId) {
        // Send the email to the direct manager — and, when the manager is a
        // vacant-position placeholder account, to its acting delegate too.
        $delegateId = User::where('id', $reportingToId)->value('access_delegate_id');
        if ($notifiable->id == $reportingToId || ($delegateId && $notifiable->id == $delegateId)) {
            return ['database', 'mail', FirebaseChannel::class];
        }
    } else {
        // If there's no reporting manager, send the email to the first active HR staff
        // member who handles this employee's location.
        $siteName = ($employee && $employee->site) ? strtolower($employee->site->name) : '';
        $address = $employee ? strtolower(($employee->perm_street ?? '') . ' ' . ($employee->temp_street ?? '') . ' ' . ($employee->address ?? '')) : '';

        $isTargetLocation = str_contains($siteName, 'ramgarh') || 
                            str_contains($siteName, 'sahnewal') || 
                            str_contains($siteName, 'jamalpur') ||
                            str_contains($address, 'jamalpur');

        // Determine which HR email should receive the main "To" email
        // Everyone outside those sites used to go to priyal@; she has switched
        // the leave broadcast off, and these now go to Suchita.
        $mainHrEmail = $isTargetLocation ? 'hr@rustx.com' : 'suchita@rustx.com';

        // Find the user with this email
        $mainHr = User::where('email', $mainHrEmail)->where('status', \App\Enums\UserAccountStatus::ACTIVE)->first();

        // Fallback to any active HR if not found
        // (skipping anyone who has muted leave, or the email would go nowhere)
        if (!$mainHr) {
            $mainHr = User::whereHas('roles', function ($query) {
                $query->where('name', 'hr');
            })->where('status', \App\Enums\UserAccountStatus::ACTIVE)->get()
              ->first(fn ($u) => !$this->leaveMutedFor($u));
        }

        // If the main HR is the current notifiable, send the mail
        if ($mainHr && $notifiable->id == $mainHr->id) {
            return ['database', 'mail', FirebaseChannel::class];
        }
    }

    // Others (including other HRs if manager is present) only get DB and Firebase notifications
    return ['database', FirebaseChannel::class];
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

    // Determine target location (Ramgarh, Sahnewal, Jamalpur)
    $siteName = ($employee && $employee->site) ? strtolower($employee->site->name) : '';
    $address = $employee ? strtolower(($employee->perm_street ?? '') . ' ' . ($employee->temp_street ?? '') . ' ' . ($employee->address ?? '')) : '';

    $isTargetLocation = str_contains($siteName, 'ramgarh') || 
                        str_contains($siteName, 'sahnewal') || 
                        str_contains($siteName, 'jamalpur') ||
                        str_contains($address, 'jamalpur');

    if ($isTargetLocation) {
        $ccEmails = ['hr@rustx.com', 'hr@rustx.net']; // Raman & Divya
    } else {
        $ccEmails = ['priyal@rustx.com', 'suchita@rustx.com']; // Priyal & Suchita
    }

    // Exclude the recipient from the CC list to avoid duplicate mails
    $ccEmails = array_filter($ccEmails, function ($email) use ($notifiable) {
        return strtolower($email) !== strtolower($notifiable->email);
    });

    if (!empty($ccEmails)) {
        $mail->cc(array_unique($ccEmails));
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
      'action_url' => url('/leaveRequests?id=' . $this->leaveRequest->id)
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
