<?php

namespace App\Notifications\NinetyDayGoal;

use App\Models\NinetyDayGoal;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

/** Sent to a goal's owner when someone else (their lead, admin/HR) replies on one of its weeks. */
class NinetyDayGoalNoteAdded extends Notification
{
    use Queueable;

    private NinetyDayGoal $goal;
    private User $author;
    private int $week;
    private string $body;

    public function __construct(NinetyDayGoal $goal, User $author, int $week, string $body)
    {
        $this->goal = $goal;
        $this->author = $author;
        $this->week = $week;
        $this->body = $body;
    }

    public function via(object $notifiable): array
    {
        // In-app only, deliberately — no mail. This app's local .env points at a real Gmail
        // account, so any mail channel here is a real send attempt to a real inbox.
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        $by = trim($this->author->first_name . ' ' . $this->author->last_name);

        return [
            'title'      => '90-Day Goals: New Reply — ' . $this->goal->client_name,
            'message'    => $by . ' replied on W' . ($this->week + 1) . ': ' . \Illuminate\Support\Str::limit($this->body, 120),
            'goal_id'    => $this->goal->id,
            'week'       => $this->week,
            'action_url' => url('/performance/ninety-day-goals/round/' . $this->goal->round_id),
        ];
    }
}
