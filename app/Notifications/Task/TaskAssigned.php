<?php

namespace App\Notifications\Task;

use App\Channels\FirebaseChannel;
use App\Channels\WebPushChannel;
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
    $this->message = (optional($task->assignedBy)->getFullName() ?? 'Someone') . ' assigned you a task: ' . $task->title;
  }

  /**
   * No 'mail' here on purpose - this is the last of the per-task emails
   * (new assignment, every recurring occurrence, every reassignment all
   * went through this one) left standing after TaskReplied/TaskStatusUpdated/
   * TaskDetailsUpdated/TaskEscalated were switched to in-app + push only.
   * A recurring daily task used to re-send this EVERY day it fired - the
   * single biggest source of "one email per task." TaskDailyDigest's
   * "Assigned to you" section (due/overdue/total pending) is the one place
   * this now reaches an inbox.
   */
  public function via(object $notifiable): array
  {
    return ['database', FirebaseChannel::class, WebPushChannel::class];
  }

  public function toMail(object $notifiable): MailMessage
  {
    return (new MailMessage)
      ->subject('New Task: ' . $this->task->title)
      ->view('emails.task_assigned', [
        'notifiable'      => $notifiable,
        'taskTitle'       => $this->task->title,
        'description'     => $this->task->description,
        'dueDate'         => $this->task->dueLabel(),
        'priority'        => $this->task->priority,
        'assignedByName'  => optional($this->task->assignedBy)->getFullName() ?? 'your organisation',
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
