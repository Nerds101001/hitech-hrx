<?php

namespace App\Notifications\Auth;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class SecurityAlertNotification extends Notification
{
    use Queueable;

    private $details;

    /**
     * Create a new notification instance.
     */
    public function __construct($details)
    {
        $this->details = $details;
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
            ->error()
            ->subject('🚩 URGENT: Security Alert - ' . $this->details['reason'])
            ->greeting('Security Alert Triggered!')
            ->line('The system has detected a brute force attempt and taken defensive action.')
            ->line('**Target Email:** ' . $this->details['email'])
            ->line('**Source IP Address:** ' . $this->details['ip'])
            ->line('**Action Taken:** ' . $this->details['action'])
            ->line('**Lock Duration:** 40 Minutes')
            ->line('**Timestamp:** ' . now()->toDayDateTimeString())
            ->action('Review Audit Logs', url('/auditLogs'))
            ->line('This is an automated security measure from Hitech HRX Portal.');
    }

    /**
     * Get the array representation of the notification.
     *
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'security_alert',
            'details' => $this->details
        ];
    }
}
