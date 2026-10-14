<?php

namespace App\Notifications\NinetyDayGoal;

use App\Models\NinetyDayGoalDeleteRequest;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

/** The requester: admin/HR approved or rejected their removal request. */
class NinetyDayGoalDeleteDecision extends Notification
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
        $clientName = optional($this->request->goal)->client_name ?: 'that customer';
        $message = 'Your request to remove "' . $clientName . '" was ' . $this->request->status . '.'
            . ($this->request->admin_remarks ? ' Remark: ' . $this->request->admin_remarks : '');

        return [
            'title'      => '90-Day Goals: Removal Request ' . ucfirst($this->request->status),
            'message'    => $message,
            'request_id' => $this->request->id,
            // Approved: the goal is gone, nothing to link to. Rejected: still there, link back to it.
            'action_url' => $this->request->status === 'rejected' && $this->request->goal
                ? url('/performance/ninety-day-goals/round/' . $this->request->goal->round_id) : null,
        ];
    }
}
