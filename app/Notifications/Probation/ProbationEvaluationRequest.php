<?php

namespace App\Notifications\Probation;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\URL;

class ProbationEvaluationRequest extends Notification
{
    use Queueable;

    private User $employee;
    private string $title;
    private string $message;

    /**
     * Create a new notification instance.
     */
    public function __construct(User $employee)
    {
        $this->employee = $employee;
        $this->title = '📋 Probation Evaluation Required: ' . $employee->name;
        $this->message = 'The probation period for ' . $employee->name . ' has ended today. Please complete the performance evaluation form to confirm their employment status.';
    }

    /**
     * Get the notification's delivery channels.
     */
    public function via(object $notifiable): array
    {
        return ['mail', 'database'];
    }

    /**
     * Get the mail representation.
     */
    public function toMail(object $notifiable): MailMessage
    {
        // Generate a signed URL for the evaluation form (optional, but good for security)
        // For now, using a standard route
        $url = route('probation.evaluate', ['id' => $this->employee->id]);

        return (new MailMessage)
            ->subject($this->title)
            ->view('emails.probation_evaluation_request', [
                'employee' => $this->employee,
                'manager' => $notifiable,
                'url' => $url,
            ]);
    }

    /**
     * Get the array representation (in-app notification).
     */
    public function toArray(object $notifiable): array
    {
        return [
            'title' => $this->title,
            'message' => $this->message,
            'employee_id' => $this->employee->id,
            'type' => 'probation_evaluation',
        ];
    }
}
