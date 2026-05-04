<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use App\Models\HRPolicyAcknowledgment;
use Illuminate\Support\Facades\Storage;

class PolicyAcknowledgedNotification extends Notification
{
    use Queueable;

    protected $acknowledgment;

    /**
     * Create a new notification instance.
     */
    public function __construct(HRPolicyAcknowledgment $acknowledgment)
    {
        $this->acknowledgment = $acknowledgment;
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

    public function toMail(object $notifiable): MailMessage
    {
        $policy = $this->acknowledgment->policy;
        $user = $this->acknowledgment->user;

        return (new MailMessage)
            ->subject('Policy Acknowledgment Confirmation: ' . $policy->title)
            ->greeting('Hello ' . $user->first_name . ',')
            ->line('This is an automated confirmation that you have read and acknowledged the following company policy:')
            ->line('**Policy:** ' . $policy->title)
            ->line('**Acknowledged At:** ' . $this->acknowledgment->acknowledged_at->format('M d, Y h:i A'))
            ->line('**IP Address:** ' . $this->acknowledgment->ip_address)
            ->line('Thank you for your cooperation.');
    }
}
