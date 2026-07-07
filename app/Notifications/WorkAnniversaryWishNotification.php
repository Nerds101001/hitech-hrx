<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\BroadcastMessage;
use Illuminate\Notifications\Notification;
use App\Models\User;

class WorkAnniversaryWishNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public $sender;
    public $message;
    public $year;

    /**
     * Create a new notification instance.
     */
    public function __construct(User $sender, $message)
    {
        $this->sender = $sender;
        $this->message = $message;
        $this->year = date('Y');
    }

    /**
     * Get the notification's delivery channels.
     */
    public function via($notifiable)
    {
        return ['database', 'broadcast'];
    }

    /**
     * Get the array representation of the notification for the database.
     */
    public function toDatabase($notifiable)
    {
        return [
            'title' => 'Work Anniversary Wish 🏅',
            'message' => 'From ' . $this->sender->first_name . ': "' . $this->message . '"',
            'type' => 'anniversary_wish',
            'sender_id' => $this->sender->id,
            'sender_name' => $this->sender->first_name . ' ' . $this->sender->last_name,
            'sender_avatar' => $this->sender->getProfilePicture(), // Resolved signed URL
            'message_raw' => $this->message,
            'year' => $this->year,
        ];
    }

    /**
     * Get the broadcast representation of the notification.
     */
    public function toBroadcast($notifiable)
    {
        return new BroadcastMessage([
            'title' => 'Work Anniversary Wish 🏅',
            'message' => 'From ' . $this->sender->first_name . ': "' . $this->message . '"',
            'type' => 'anniversary_wish',
            'sender_id' => $this->sender->id,
            'sender_name' => $this->sender->first_name . ' ' . $this->sender->last_name,
            'sender_avatar' => $this->sender->getProfilePicture(), // Resolved signed URL
            'message_raw' => $this->message,
            'year' => $this->year,
            'notification_id' => $this->id,
        ]);
    }
}
