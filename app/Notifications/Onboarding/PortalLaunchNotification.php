<?php

namespace App\Notifications\Onboarding;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class PortalLaunchNotification extends Notification
{
    use Queueable;

    private User $user;
    private string $password;
    private string $subject;

    public function __construct(User $user, string $password)
    {
        $this->user     = $user;
        $this->password = $password;
        $this->subject  = '🚀 Welcome to the Next Gen: The Hitech HRX Portal is Now Live!';
    }

    public function via(object $notifiable): array
    {
        return ['mail', 'database'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject($this->subject)
            ->view('emails.portal_launch', [
                'user'     => $this->user,
                'password' => $this->password,
            ]);
    }

    public function toArray(object $notifiable): array
    {
        return [
            'title'   => $this->subject,
            'message' => 'The Hitech HRX Portal is now live! Please complete your onboarding.',
        ];
    }
}
