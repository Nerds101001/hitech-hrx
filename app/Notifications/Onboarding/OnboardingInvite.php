<?php

namespace App\Notifications\Onboarding;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Str;

class OnboardingInvite extends Notification
{
    use Queueable;

    private User $user;
    private string $password;
    private string $title;
    private string $message;

    /**
     * Create a new notification instance.
     * Password is always freshly generated and passed in — never reused.
     */
    public function __construct(User $user, string $password)
    {
        $this->user     = $user;
        $this->password = $password;
        $this->title    = '🚀 Welcome to the Next Gen: The Hitech HRX Portal is Now Live!';
        $this->message  = 'The wait is finally over! 🌟 We are officially launching the all-new Hitech HRX Portal. Hi ' . $user->first_name . ', we are thrilled to have you part of this transformation!';
    }

    /**
     * Get the notification's delivery channels.
     */
    public function via(object $notifiable): array
    {
        return ['mail', 'database'];
    }

    /**
     * Get the mail representation — uses our premium custom template.
     */
    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject($this->title)
            ->view('emails.onboarding_invite', [
                'user'     => $this->user,
                'password' => $this->password,
            ]);
    }

    /**
     * Get the array representation (in-app notification).
     */
    public function toArray(object $notifiable): array
    {
        return [
            'title'               => $this->title,
            'message'             => $this->message,
            'onboarding_deadline' => $this->user->onboarding_deadline,
        ];
    }

    public function toDatabase($notifiable): array
    {
        return [
            'title'   => $this->title,
            'message' => $this->message,
            'user_id' => $this->user->id,
        ];
    }
}
