<?php

namespace App\Notifications\Task;

use App\Channels\FirebaseChannel;
use App\Channels\WebPushChannel;
use App\Models\Task;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Sent to the assignee's MANAGER (not the assignee) once a task has stayed
 * open well past its deadline - config('tasks.escalate_after_days'). Sent
 * once per task, to whoever is the manager at that moment; see
 * EscalateOverdueTasks, which stamps escalated_at.
 */
class TaskEscalated extends Notification
{
  use Queueable;

  private Task $task;
  private int $daysOverdue;
  private string $title;
  private string $message;

  public function __construct(Task $task, int $daysOverdue)
  {
    $this->task = $task;
    $this->daysOverdue = $daysOverdue;
    $this->title = 'Task Overdue in Your Team';
    $this->message = (optional($task->user)->getFullName() ?? 'Someone') . '\'s task "' . $task->title
      . '" is ' . $daysOverdue . ' day' . ($daysOverdue === 1 ? '' : 's') . ' overdue and still open.';
  }

  /**
   * No 'mail' here on purpose - a separate escalation email per manager per
   * task was exactly what was costing the most mail volume. In-app + push
   * stay instant for the manager; EscalationDeskDigest sends the ONE
   * consolidated email for the whole run, to the escalation desk.
   */
  public function via(object $notifiable): array
  {
    return ['database', FirebaseChannel::class, WebPushChannel::class];
  }

  public function toMail(object $notifiable): MailMessage
  {
    return (new MailMessage)
      ->subject('Escalation: ' . (optional($this->task->user)->getFullName() ?? 'a team member') . ' - ' . $this->task->title)
      ->view('emails.task_escalated', [
        'notifiable'   => $notifiable,
        'assigneeName' => optional($this->task->user)->getFullName() ?? 'A team member',
        'taskTitle'    => $this->task->title,
        'description'  => $this->task->description,
        'dueDate'      => $this->task->dueLabel(),
        'daysOverdue'  => $this->daysOverdue,
        'actionUrl'    => url('/task-management?user_id=' . $this->task->user_id . '&status=open'),
      ]);
  }

  public function toArray(object $notifiable): array
  {
    return [
      'title' => $this->title,
      'message' => $this->message,
      'task_id' => $this->task->id,
      'action_url' => url('/task-management?user_id=' . $this->task->user_id . '&status=open'),
    ];
  }

  public function toFirebase(): array
  {
    return ['title' => $this->title, 'body' => $this->message];
  }
}
