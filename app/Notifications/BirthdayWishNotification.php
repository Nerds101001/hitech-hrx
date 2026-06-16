<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\BroadcastMessage;
use Illuminate\Notifications\Notification;
use App\Models\User;

class BirthdayWishNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public $sender;
    public $message;
    public $year;

    /**
     * Create a new notification instance.
     *
     * @return void
     */
    public function __construct(User $sender, $message)
    {
        $this->sender = $sender;
        $this->message = $message;
        $this->year = date('Y');
    }

    /**
     * Get the notification's delivery channels.
     *
     * @param  mixed  $notifiable
     * @return array
     */
    public function via($notifiable)
    {
        return ['database', 'broadcast'];
    }

    /**
     * Get the array representation of the notification for the database.
     *
     * @param  mixed  $notifiable
     * @return array
     */
    public function toDatabase($notifiable)
    {
        return [
            'type' => 'birthday_wish',
            'sender_id' => $this->sender->id,
            'sender_name' => $this->sender->first_name . ' ' . $this->sender->last_name,
            'sender_avatar' => $this->sender->profile_picture,
            'message' => $this->message,
            'year' => $this->year,
        ];
    }

    /**
     * Get the broadcast representation of the notification.
     *
     * @param  mixed  $notifiable
     * @return BroadcastMessage
     */
    public function toBroadcast($notifiable)
    {
        return new BroadcastMessage([
            'type' => 'birthday_wish',
            'sender_id' => $this->sender->id,
            'sender_name' => $this->sender->first_name . ' ' . $this->sender->last_name,
            'sender_avatar' => $this->sender->profile_picture,
            'message' => $this->message,
            'year' => $this->year,
            'notification_id' => $this->id,
        ]);
    }
}
