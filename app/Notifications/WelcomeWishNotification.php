<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\BroadcastMessage;
use Illuminate\Notifications\Notification;
use App\Models\User;

/**
 * A colleague welcoming a new joiner from the announcement on their dashboard.
 *
 * Same shape and channels as the birthday and anniversary wishes — in-app only,
 * no mail — so the notification hub renders it without any special handling.
 */
class WelcomeWishNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public $sender;
    public $message;

    public function __construct(User $sender, $message)
    {
        $this->sender = $sender;
        $this->message = $message;
    }

    public function via($notifiable)
    {
        return ['database', 'broadcast'];
    }

    /** The payload both channels share. */
    private function payload(): array
    {
        return [
            'title' => 'Welcome Message 👋',
            'message' => 'From ' . $this->sender->first_name . ': "' . $this->message . '"',
            'type' => 'welcome_wish',
            'sender_id' => $this->sender->id,
            'sender_name' => $this->sender->first_name . ' ' . $this->sender->last_name,
            'sender_avatar' => $this->sender->getProfilePicture(),
            'message_raw' => $this->message,
        ];
    }

    public function toDatabase($notifiable)
    {
        return $this->payload();
    }

    public function toBroadcast($notifiable)
    {
        return new BroadcastMessage($this->payload() + ['notification_id' => $this->id]);
    }
}
