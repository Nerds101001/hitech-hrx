<?php

namespace App\Notifications\Task;

use App\Channels\FirebaseChannel;
use App\Channels\WebPushChannel;
use App\Models\Task;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * A heads-up to the assignee before a task is due - sent once (see
 * NotifyDueSoonTasks, which stamps due_soon_notified_at). Distinct from
 * TaskOverdue, which only fires once the deadline has already passed.
 */
class TaskDueSoon extends Notification
{
  use Queueable;

  private Task $task;
  private string $title;
  private string $message;

  public function __construct(Task $task)
  {
    $this->task = $task;
    $this->title = 'Task Due Soon';
    $this->message = 'Your task "' . $task->title . '" is due ' . $task->dueLabel() . '.';
  }

  /** No 'mail' - per-task emails are consolidated into TaskDailyDigest. In-app + push stay instant. */
  public function via(object $notifiable): array
  {
    return ['database', FirebaseChannel::class, WebPushChannel::class];
  }

  public function toMail(object $notifiable): MailMessage
  {
    return (new MailMessage)
      ->subject('Due soon: ' . $this->task->title)
      ->view('emails.task_due_soon', [
        'notifiable'  => $notifiable,
        'taskTitle'   => $this->task->title,
        'description' => $this->task->description,
        'dueDate'     => $this->task->dueLabel(),
        'actionUrl'   => url('/my-tasks'),
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
