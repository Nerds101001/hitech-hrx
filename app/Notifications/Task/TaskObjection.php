<?php

namespace App\Notifications\Task;

use App\Channels\FirebaseChannel;
use App\Channels\WebPushChannel;
use App\Models\Task;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class TaskObjection extends Notification
{
  use Queueable;

  private Task $task;
  private string $title;
  private string $message;

  public function __construct(Task $task)
  {
    $this->task = $task;
    $this->title = 'Objection Raised On Your Task';
    $this->message = (optional($task->objectedBy)->getFullName() ?? 'An admin')
      . ' sent "' . $task->title . '" back to you: ' . $task->objection_notes;
  }

  /** No 'mail' - per-task emails are consolidated into TaskDailyDigest. In-app + push stay instant. */
  public function via(object $notifiable): array
  {
    return ['database', FirebaseChannel::class, WebPushChannel::class];
  }

  public function toMail(object $notifiable): MailMessage
  {
    return (new MailMessage)
      ->subject('Task Sent Back: ' . $this->task->title)
      ->view('emails.task_objection', [
        'notifiable'      => $notifiable,
        'taskTitle'       => $this->task->title,
        'objectionNotes'  => $this->task->objection_notes,
        'objectedByName'  => optional($this->task->objectedBy)->getFullName() ?? 'An admin',
        'actionUrl'       => url('/my-tasks'),
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
