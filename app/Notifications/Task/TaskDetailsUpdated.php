<?php

namespace App\Notifications\Task;

use App\Channels\FirebaseChannel;
use App\Channels\WebPushChannel;
use App\Models\Task;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/** A task's own details (title/description/due date/priority) changed — not a reassignment, that's TaskAssigned. */
class TaskDetailsUpdated extends Notification
{
  use Queueable;

  private Task $task;
  private string $summary;
  private ?User $changedBy;
  private string $title;
  private string $message;

  public function __construct(Task $task, string $summary, ?User $changedBy)
  {
    $this->task = $task;
    $this->summary = $summary;
    $this->changedBy = $changedBy;
    $this->title = 'Task Updated';
    $this->message = (optional($changedBy)->getFullName() ?? 'Someone')
      . ' updated "' . $task->title . '": ' . $summary;
  }

  /** No 'mail' - see TaskReplied for why. In-app + push only; the daily digest covers email. */
  public function via(object $notifiable): array
  {
    return ['database', FirebaseChannel::class, WebPushChannel::class];
  }

  public function toMail(object $notifiable): MailMessage
  {
    return (new MailMessage)
      ->subject('Task Updated: ' . $this->task->title)
      ->view('emails.task_details_updated', [
        'notifiable'    => $notifiable,
        'taskTitle'     => $this->task->title,
        'summary'       => $this->summary,
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
