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
        $mail = (new MailMessage)
            ->subject("Daily Team Out Today Digest - {$date}")
            ->greeting("Hello {$notifiable->first_name},")
            ->line("Here is your daily update on team availability for **{$this->teamName}** today, {$date}.");

        if ($this->leavesToday->isEmpty()) {
            $mail->line("Great news! Everyone in your team is available today.");
        } else {
            $mail->line("The following team members are on leave today:");
            foreach ($this->leavesToday as $leave) {
                $mail->line("• **{$leave->user->getFullName()}** ({$leave->leaveType->name})");
            }
        }

        return $mail->line('Wishing you a productive day ahead!')
            ->line('Regards,')
            ->line('HRX Operations');
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
