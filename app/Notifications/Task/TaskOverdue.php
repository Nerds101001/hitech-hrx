<?php

namespace App\Notifications\Task;

use App\Channels\FirebaseChannel;
use App\Channels\WebPushChannel;
use App\Models\Task;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Sent once, the first time the daily check finds a task still open past its
 * due date — not a nag every day. See NotifyOverdueTasks, which stamps
 * delay_notified_at right after this goes out so it does not repeat.
 */
class TaskOverdue extends Notification
{
  use Queueable;

  private Task $task;
  private string $title;
  private string $message;

  public function __construct(Task $task)
  {
    $this->task = $task;
    $this->title = 'Task Overdue';
    $this->message = 'Your task "' . $task->title . '" was due ' . $task->dueLabel() . ' and is still open.';
  }

  public function via(object $notifiable): array
  {
    return ['database', 'mail', FirebaseChannel::class, WebPushChannel::class];
  }

  public function toMail(object $notifiable): MailMessage
  {
    return (new MailMessage)
      ->subject('Overdue: ' . $this->task->title)
      ->view('emails.task_overdue', [
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
