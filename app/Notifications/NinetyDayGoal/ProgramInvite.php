<?php

namespace App\Notifications\NinetyDayGoal;

use App\Models\NinetyDayProgram;
use App\Models\NinetyDayRound;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/** Sent the moment an admin creates a 90-Day Goals program and invites someone into it. */
class ProgramInvite extends Notification
{
    use Queueable;

    private NinetyDayProgram $program;
    private NinetyDayRound $round;
    private string $title;
    private string $message;

    public function __construct(NinetyDayProgram $program, NinetyDayRound $round)
    {
        $this->program = $program;
        $this->round = $round;
        $this->title = 'New 90-Day Goals program: ' . $program->name;
        $this->message = ($program->creator->getFullName() ?? 'An admin') . ' added you to "' . $program->name . '" — add your target customers within 3 days.';
    }

    public function via(object $notifiable): array
    {
        return ['database', 'mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('You have been added to "' . $this->program->name . '"')
            ->view('emails.ninety_day_goal_invite', [
                'notifiable'    => $notifiable,
                'programName'   => $this->program->name,
                'startsOn'      => $this->round->starts_on->format('d M Y'),
                'endsOn'        => $this->round->ends_on->format('d M Y'),
                'deadline'      => $this->round->starts_on->copy()->addDays(3)->format('d M Y'),
                'createdByName' => optional($this->program->creator)->getFullName() ?? 'your organisation',
                'actionUrl'     => url('/performance/ninety-day-goals'),
            ]);
    }

    public function toArray(object $notifiable): array
    {
        return [
            'title'       => $this->title,
            'message'     => $this->message,
            'program_id'  => $this->program->id,
            'round_id'    => $this->round->id,
            'action_url'  => url('/performance/ninety-day-goals'),
        ];
    }
}
