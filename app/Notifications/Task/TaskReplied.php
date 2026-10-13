<?php

namespace App\Notifications\Task;

use App\Channels\FirebaseChannel;
use App\Channels\WebPushChannel;
use App\Models\Task;
use App\Models\TaskUpdate;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/** Someone replied on a task's thread — sent to the counterpart (not the replier). */
class TaskReplied extends Notification
{
  use Queueable;

  private Task $task;
  private TaskUpdate $update;
  private string $title;
  private string $message;

  public function __construct(Task $task, TaskUpdate $update)
  {
    $this->task = $task;
    $this->update = $update;
    $this->title = 'New Reply On Your Task';
    $this->message = (optional($update->createdBy)->getFullName() ?? 'Someone')
      . ' replied on "' . $task->title . '": ' . \Illuminate\Support\Str::limit((string) $update->comment, 120);
  }

  /**
   * No 'mail' here on purpose - a separate email per reply was exactly what
   * the user asked to stop getting. The in-app bell and push stay instant;
   * TaskDailyDigest is the one daily email that rolls this up.
   */
  public function via(object $notifiable): array
  {
    return ['database', FirebaseChannel::class, WebPushChannel::class];
  }

  public function toMail(object $notifiable): MailMessage
  {
    return (new MailMessage)
      ->subject('New Reply: ' . $this->task->title)
      ->view('emails.task_replied', [
        'notifiable'    => $notifiable,
        'taskTitle'     => $this->task->title,
        'comment'       => $this->update->comment,
        'repliedByName' => optional($this->update->createdBy)->getFullName() ?? 'Someone',
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
