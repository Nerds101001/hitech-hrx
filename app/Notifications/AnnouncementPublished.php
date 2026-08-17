<?php

namespace App\Notifications;

use App\Channels\FirebaseChannel;
use App\Models\Announcement;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class AnnouncementPublished extends Notification
{
    use Queueable;

    public function __construct(private Announcement $announcement)
    {
    }

    /**
     * Everyone in the audience gets the in-app + push notification. Email is
     * opt-in per announcement (HR ticks "Also send by email"), and always sent
     * for critical ones so an urgent notice is never missed.
     */
    public function via(object $notifiable): array
    {
        $channels = ['database', FirebaseChannel::class];

        $wantsEmail = $this->announcement->notify_email
            || $this->announcement->priority === 'critical';

        if ($wantsEmail && !empty($notifiable->email)) {
            $channels[] = 'mail';
        }

        return $channels;
    }

    public function toMail(object $notifiable): MailMessage
    {
        $a = $this->announcement;

        $subjectPrefix = match ($a->priority) {
            'critical' => '[URGENT] ',
            'high'     => '[Important] ',
            default    => '',
        };

        return (new MailMessage)
            ->subject($subjectPrefix . $a->title)
            ->view('emails.announcement_published', [
                'notifiable'   => $notifiable,
                'title'        => $a->title,
                'content'      => $a->content,
                'isHtml'       => (bool) $a->is_html,
                'type'         => ucfirst($a->type),
                'priority'     => ucfirst($a->priority),
                'author'       => $a->createdBy?->getFullName() ?? 'HR',
                'startDate'    => $a->start_date?->format('d M Y'),
                'endDate'      => $a->end_date?->format('d M Y'),
                'actionUrl'    => url('/announcements/feed'),
                'needsAck'     => (bool) $a->requires_acknowledgment,
            ]);
    }

    public function toDatabase($notifiable): array
    {
        return [
            'title'      => $this->announcement->title,
            'message'    => \Illuminate\Support\Str::limit(strip_tags($this->announcement->content), 140),
            'type'       => $this->announcement->type,
            'priority'   => $this->announcement->priority,
            'action_url' => url('/announcements/feed'),
        ];
    }

    public function toFirebase()
    {
        return [
            'title' => $this->announcement->title,
            'body'  => \Illuminate\Support\Str::limit(strip_tags($this->announcement->content), 120),
        ];
    }

    public function toArray(object $notifiable): array
    {
        return [];
    }
}
