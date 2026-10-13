<?php

namespace App\Notifications\Task;

use App\Channels\FirebaseChannel;
use App\Channels\WebPushChannel;
use App\Models\Task;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Sent once, the day the assignee's own completion_expected_date arrives -
 * the date they said they'd finish by, not the assigner's due_date. See
 * NotifyExpectedCompletionDate, which stamps expected_date_notified_at right
 * after this goes out so it does not repeat.
 */
class TaskExpectedDateReached extends Notification
{
  use Queueable;

  private Task $task;
  private string $title;
  private string $message;

  public function __construct(Task $task)
  {
    $this->task = $task;
    $this->title = 'Expected Completion Date Reached';
    $this->message = 'You expected to finish "' . $task->title . '" by today (' . $task->completion_expected_date->format('d M Y') . ') — update its status.';
  }

  /** No 'mail' - per-task emails are consolidated into TaskDailyDigest. In-app + push stay instant. */
  public function via(object $notifiable): array
  {
    return ['database', FirebaseChannel::class, WebPushChannel::class];
  }

  public function toMail(object $notifiable): MailMessage
  {
    return (new MailMessage)
      ->subject('Expected completion date reached: ' . $this->task->title)
      ->view('emails.task_expected_date', [
        'notifiable'  => $notifiable,
        'taskTitle'   => $this->task->title,
        'description' => $this->task->description,
        'dueDate'     => $this->task->completion_expected_date->format('d M Y'),
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
