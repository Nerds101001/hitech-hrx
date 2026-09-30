<?php

namespace App\Notifications\Leave;

use App\Channels\FirebaseChannel;
use App\Models\OutdoorDuty;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

/**
 * Tells the manager and HR that an outdoor duty request is waiting. It used to
 * be saved with no alert at all, so approvers only saw it if they happened to
 * open Leave Approvals. Bell + phone push only - no email.
 */
class NewOutdoorDuty extends Notification
{
  use Queueable, RespectsLeaveMute;

  public function __construct(private OutdoorDuty $duty)
  {
  }

  public function via(object $notifiable): array
  {
    // Same switch as leave: muted means nothing, unless it is theirs to approve.
    if (!$this->isApproverOf($notifiable, $this->duty->user) && $this->leaveMutedFor($notifiable)) {
      return [];
    }

    return ['database', FirebaseChannel::class];
  }

  private function message(): string
  {
    return ($this->duty->user?->getFullName() ?? 'An employee') . ' applied for outdoor duty on '
      . $this->duty->date->format('d M, Y') . ($this->duty->location ? ' (' . $this->duty->location . ')' : '');
  }

  public function toDatabase($notifiable): array
  {
    return [
      'title' => 'New Outdoor Duty Request',
      'message' => $this->message(),
      'action_url' => url('/leaveRequests'),
    ];
  }

  public function toFirebase()
  {
    return [
      'title' => 'New Outdoor Duty Request',
      'body' => $this->message(),
    ];
  }
}
