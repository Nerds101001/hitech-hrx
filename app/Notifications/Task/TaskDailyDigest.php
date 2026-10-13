<?php

namespace App\Notifications\Task;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * The one daily task email — replaces separate reply/status/edit emails.
 * Mail only, deliberately: TaskReplied/TaskStatusUpdated/TaskDetailsUpdated
 * still fire instantly for the in-app bell and push, but no longer send
 * mail (see their via()) — this digest is the single inbox touch for tasks.
 */
class TaskDailyDigest extends Notification
{
  use Queueable;

  private array $data;

  public function __construct(array $data)
  {
    $this->data = $data;
  }

  public function via(object $notifiable): array
  {
    return ['mail'];
  }

  public function toMail(object $notifiable): MailMessage
  {
    return (new MailMessage)
      ->subject('Your Daily Task Digest — ' . now()->format('d M Y'))
      ->view('emails.task_daily_digest', array_merge($this->data, [
        'notifiable' => $notifiable,
        'actionUrl'  => url('/my-tasks'),
        'taskMgmtUrl' => url('/task-management'),
      ]));
  }
}
