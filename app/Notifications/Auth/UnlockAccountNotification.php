<?php

namespace App\Notifications\Auth;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class UnlockAccountNotification extends Notification
{
    use Queueable;

    private $unlockUrl;

    /**
     * Create a new notification instance.
     */
    public function __construct($unlockUrl)
    {
        $this->unlockUrl = $unlockUrl;
    }

    /**
     * Get the notification's delivery channels.
     *
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    /**
     * Get the mail representation of the notification.
     */
    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('🔐 Your Account Unlock Link - Hitech HRX')
            ->greeting('Hello!')
            ->line('We received a request to unlock your Hitech HRX account.')
            ->line('If this was you, please click the button below to restore your access instantly.')
            ->action('Unlock My Account', $this->unlockUrl)
            ->line('This secure link will expire in 15 minutes.')
            ->line('If you did not request this, please ignore this email and your account will remain locked for your safety.');
    }

    /**
     * Get the array representation of the notification.
     *
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'unlock_account'
        ];
    }
}
