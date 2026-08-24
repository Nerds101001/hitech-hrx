<?php

namespace App\Notifications\Task;

use App\Channels\FirebaseChannel;
use App\Models\Task;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class TaskAssigned extends Notification
{
  use Queueable;

  private Task $task;
  private string $title;
  private string $message;

  public function __construct(Task $task)
  {
    $this->task = $task;
    $this->title = 'New Task Assigned';
    $this->message = ($task->assignedBy->getFullName() ?? 'Someone') . ' assigned you a task: ' . $task->title;
  }

  public function via(object $notifiable): array
  {
    return ['database', 'mail', FirebaseChannel::class];
  }

  public function toMail(object $notifiable): MailMessage
  {
    $mail = (new MailMessage)
      ->subject('New Task: ' . $this->task->title)
      ->line('You have been assigned a new task by ' . (optional($this->task->assignedBy)->getFullName() ?? 'your organisation') . '.')
      ->line('**' . $this->task->title . '**');

    if ($this->task->description) {
      $mail->line($this->task->description);
    }

    if ($this->task->due_date) {
      $mail->line('Due: ' . $this->task->due_date->format('d M Y'));
    }

    return $mail->action('View My Tasks', url('/my-tasks'))
      ->line('You can mark it complete and leave a remark once it is done.');
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
