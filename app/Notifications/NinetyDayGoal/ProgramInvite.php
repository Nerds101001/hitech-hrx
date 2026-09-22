<?php

namespace App\Notifications\NinetyDayGoal;

use App\Models\NinetyDayProgram;
use App\Models\NinetyDayRound;
use Illuminate\Bus\Queueable;
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
        $this->message = (optional($program->creator)->getFullName() ?? 'An admin') . ' added you to "' . $program->name . '" — add your target customers within 3 days.';
    }

    public function via(object $notifiable): array
    {
        // In-app only, deliberately — no mail. This app's local .env points at a real Gmail
        // account, so any mail channel here is a real send attempt, and it isn't needed anyway:
        // Goals::createProgram() already gives everyone enrolled an "add your target customers"
        // task in the existing Task Management module, which is the actual notification.
        return ['database'];
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
