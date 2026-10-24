<?php

namespace App\Notifications\Auth;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class AccountNotLockedNotification extends Notification
{
    use Queueable;

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Account Unlock Request - Hi Tech Group')
            ->line('We received a request to unlock your account.')
            ->line('Your account is not currently locked, so no unlock link is needed - you can sign in as usual.')
            ->line('If you did not make this request, you can ignore this email.');
    }

    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'account_not_locked',
        ];
    }
}
