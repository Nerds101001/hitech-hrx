<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Carbon\Carbon;

class TeamDailyDigest extends Notification
{
    use Queueable;

    protected $leavesToday;
    protected $teamName;

    /**
     * Create a new notification instance.
     */
    public function __construct($leavesToday, $teamName)
    {
        $this->leavesToday = $leavesToday;
        $this->teamName = $teamName;
    }

    /**
     * Get the notification's delivery channels.
     *
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail', 'database'];
    }

    /**
     * Get the mail representation of the notification.
     */
    public function toMail(object $notifiable): MailMessage
    {
        $date = Carbon::today()->format('d M, Y');
        return (new MailMessage)
            ->subject("Daily Team Out Today Digest - {$date}")
            ->view('emails.team_daily_digest', [
                'notifiable' => $notifiable,
                'date' => $date,
                'teamName' => $this->teamName,
                'leavesToday' => $this->leavesToday
            ]);
    }

    /**
     * Get the array representation of the notification.
     */
    public function toArray(object $notifiable): array
    {
        return [
            'title' => 'Daily Team Digest',
            'message' => count($this->leavesToday) . ' team members are on leave today.',
            'team_name' => $this->teamName
        ];
    }
}
