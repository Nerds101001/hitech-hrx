<?php

namespace App\Notifications\NinetyDay;

use App\Channels\FirebaseChannel;
use App\Channels\WebPushChannel;
use App\Models\NinetyDayRound;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * "Finish adding your 90-Day customers" - emailed once when the add window has
 * ~12 hours left and the person is still under the minimum. See
 * NinetyDayGoalService::roundsNeedingAddReminder and the daily command.
 */
class AddCustomersReminder extends Notification
{
    use Queueable;

    private int $count;
    private int $min;
    private string $closesAt;
    private string $title;
    private string $message;

    public function __construct(NinetyDayRound $round, int $count, int $min, \Carbon\Carbon $closesAt)
    {
        $this->count = $count;
        $this->min = $min;
        $this->closesAt = $closesAt->format('D d M Y, h:i A');
        $this->title = 'Add your 90-Day customers';
        $this->message = 'You have added ' . $count . ' of ' . $min . ' customers. The window closes ' . $this->closesAt . ' - please add the rest.';
    }

    public function via(object $notifiable): array
    {
        return ['database', 'mail', FirebaseChannel::class, WebPushChannel::class];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Reminder: add your 90-Day customers before ' . $this->closesAt)
            ->view('emails.ninety_day_add_customers_reminder', [
                'notifiable' => $notifiable,
                'count'      => $this->count,
                'min'        => $this->min,
                'shortBy'    => max(0, $this->min - $this->count),
                'closesAt'   => $this->closesAt,
                'actionUrl'  => url('/performance/ninety-day-goals'),
            ]);
    }

    public function toArray(object $notifiable): array
    {
        return [
            'title'      => $this->title,
            'message'    => $this->message,
            'action_url' => url('/performance/ninety-day-goals'),
        ];
    }

    public function toFirebase(): array
    {
        return ['title' => $this->title, 'body' => $this->message];
    }
}
