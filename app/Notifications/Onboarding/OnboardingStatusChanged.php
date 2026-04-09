<?php

namespace App\Notifications\Onboarding;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class OnboardingStatusChanged extends Notification
{
    use Queueable;

    private User $user;
    private string $status;
    private string $title;
    private string $message;
    private ?string $notes;

    /**
     * Create a new notification instance.
     */
    public function __construct(User $user, string $status, ?string $notes = null)
    {
        $this->user = $user;
        $this->status = $status;
        $this->notes = $notes;
        
        if ($status === 'approved') {
            $this->title = 'Onboarding Approved!';
            $this->message = 'Congratulations! Your onboarding has been approved. You now have full access to the employee dashboard.';
        } else {
            $this->title = 'Onboarding Resubmission Required';
            $this->message = 'Your onboarding submission needs some updates. Please review the notes and resubmit the form.';
        }
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
        $mail = (new MailMessage)
            ->subject($this->title)
            ->greeting('Hi ' . $this->user->first_name . ',')
            ->line($this->message);

        if ($this->notes) {
            $mail->line('Notes from HR: ' . $this->notes);
        }

        return $mail->action('View Dashboard', url('/login'));
    }

    /**
     * Get the array representation of the notification.
     *
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'title' => $this->title,
            'message' => $this->message,
            'status' => $this->status,
            'notes' => $this->notes,
        ];
    }

    public function toDatabase($notifiable): array
    {
        return [
            'title' => $this->title,
            'message' => $this->message,
            'status' => $this->status,
            'notes' => $this->notes,
        ];
    }
}
