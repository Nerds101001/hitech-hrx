<?php

namespace App\Notifications\NinetyDayGoal;

use App\Models\NinetyDayGoalDeleteRequest;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

/** Admin/HR: a salesperson has asked to remove a customer from their 90-Day program. */
class NinetyDayGoalDeleteRequested extends Notification
{
    use Queueable;

    private NinetyDayGoalDeleteRequest $request;

    public function __construct(NinetyDayGoalDeleteRequest $request)
    {
        $this->request = $request;
    }

    public function via(object $notifiable): array
    {
        // In-app only, deliberately — no mail. This app's local .env points at a real Gmail
        // account, so any mail channel here is a real send attempt to a real inbox.
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        $goal = $this->request->goal;
        $by = trim($this->request->requestedBy->first_name . ' ' . $this->request->requestedBy->last_name);

        return [
            'title'      => '90-Day Goals: Removal Requested',
            'message'    => $by . ' asked to remove "' . $goal->client_name . '" from their 90-Day program.',
            'request_id' => $this->request->id,
            'action_url' => url('/performance/ninety-day-goals/round/' . $goal->round_id),
        ];
    }
}
