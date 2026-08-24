<?php

namespace App\Notifications\Task;

use App\Channels\FirebaseChannel;
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
    $this->message = 'Your task "' . $task->title . '" was due ' . $task->due_date->format('d M Y') . ' and is still open.';
  }

  public function via(object $notifiable): array
  {
    return ['database', 'mail', FirebaseChannel::class];
  }

  public function toMail(object $notifiable): MailMessage
  {
    $mail = (new MailMessage)
      ->subject('Overdue: ' . $this->task->title)
      ->line('This task was due on **' . $this->task->due_date->format('d M Y') . '** and has not been marked complete.')
      ->line('**' . $this->task->title . '**');

    if ($this->task->description) {
      $mail->line($this->task->description);
    }

    return $mail->action('Mark It Complete', url('/my-tasks'))
      ->line('If it is already done, mark it complete with a remark so this does not keep showing as pending.');
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
