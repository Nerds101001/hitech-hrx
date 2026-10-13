<?php

namespace App\Notifications\Task;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * The ONE email a run of tasks:escalate-overdue sends - every escalation
 * from that run, in one list, to the escalation desk. Replaces a separate
 * email per manager per task (TaskEscalated still notifies each manager
 * in-app + push, just not mail - see its via()).
 */
class EscalationDeskDigest extends Notification
{
  use Queueable;

  /** @param array<int, array{taskId:int,taskTitle:string,assigneeName:string,managerName:string,daysOverdue:int,dueLabel:?string}> $escalations */
  private array $escalations;

  public function __construct(array $escalations)
  {
    $this->escalations = $escalations;
  }

  public function via(object $notifiable): array
  {
    return ['mail'];
  }

  public function toMail(object $notifiable): MailMessage
  {
    $count = count($this->escalations);

    return (new MailMessage)
      ->subject('Escalation Desk: ' . $count . ' task' . ($count === 1 ? '' : 's') . ' escalated today')
      ->view('emails.escalation_desk_digest', [
        'notifiable'   => $notifiable,
        'escalations'  => $this->escalations,
        'count'        => $count,
        'actionUrl'    => url('/task-management'),
      ]);
  }
}
