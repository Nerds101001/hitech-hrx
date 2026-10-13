<?php

namespace App\Notifications\Task;

use App\Channels\FirebaseChannel;
use App\Channels\WebPushChannel;
use App\Models\Task;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * A task's status moved (Pending / Partially Done / Completed / Resubmitted /
 * or an admin-side status) — sent to the counterpart: the assigner when the
 * assignee updates it, the assignee when an admin updates it on their behalf.
 */
class TaskStatusUpdated extends Notification
{
  use Queueable;

  private Task $task;
  private string $statusLabel;
  private ?User $changedBy;
  private ?string $remarks;
  private string $title;
  private string $message;

  public function __construct(Task $task, string $statusLabel, ?User $changedBy, ?string $remarks = null)
  {
    $this->task = $task;
    $this->statusLabel = $statusLabel;
    $this->changedBy = $changedBy;
    $this->remarks = $remarks;
    $this->title = 'Task Status Updated';
    $this->message = (optional($changedBy)->getFullName() ?? 'Someone')
      . ' marked "' . $task->title . '" as ' . $statusLabel . '.';
  }

  /** No 'mail' - see TaskReplied for why. In-app + push only; the daily digest covers email. */
  public function via(object $notifiable): array
  {
    return ['database', FirebaseChannel::class, WebPushChannel::class];
  }

  public function toMail(object $notifiable): MailMessage
  {
    return (new MailMessage)
      ->subject('Status Update: ' . $this->task->title)
      ->view('emails.task_status_updated', [
        'notifiable'    => $notifiable,
        'taskTitle'     => $this->task->title,
        'statusLabel'   => $this->statusLabel,
        'remarks'       => $this->remarks,
        'changedByName' => optional($this->changedBy)->getFullName() ?? 'Someone',
        'actionUrl'     => url('/my-tasks'),
      ]);
  }

  public function toArray(object $notifiable): array
  {
    return [
      'title' => $this->title,
      'message' => $this->message,
      'task_id' => $this->task->id,
      'action_url' => url('/my-tasks'),
    ];
  }

  public function toFirebase(): array
  {
    return ['title' => $this->title, 'body' => $this->message];
  }
}
