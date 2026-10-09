<?php

namespace App\Notifications\NinetyDayGoal;

use App\Models\NinetyDayProgram;
use Carbon\Carbon;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

/** Sent when an admin taps "Send Reminder" on a program's Overview page for a specific week. */
class WeekReminder extends Notification
{
    use Queueable;

    private NinetyDayProgram $program;
    private int $week;
    private Carbon $dueOn;

    public function __construct(NinetyDayProgram $program, int $week, Carbon $dueOn)
    {
        $this->program = $program;
        $this->week = $week;
        $this->dueOn = $dueOn;
    }

    public function via(object $notifiable): array
    {
        // In-app only, deliberately — no mail. This app's local .env points at a real Gmail
        // account, so any mail channel here is a real send attempt to a real inbox.
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        return [
            'title'      => 'Week W' . ($this->week + 1) . ' updates are pending — ' . $this->program->name,
            'message'    => 'Please submit this week\'s status for every customer in "' . $this->program->name . '" (due ' . $this->dueOn->format('d M Y') . ').',
            'program_id' => $this->program->id,
            'week'       => $this->week,
            'action_url' => url('/performance/ninety-day-goals/my-program'),
        ];
    }
}
