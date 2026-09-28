<?php

namespace App\Mail;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Sent when a new joiner passes the gating training phases and their account
 * is released automatically.
 *
 * This reports something that has already happened - the account is open by
 * the time this goes out - so it is deliberately not worded as a request to
 * go and activate anyone.
 */
class TrainingCompleted extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public User $employee,
        public int $moduleCount,
        public ?float $averageScore,
        public bool $accountActivated,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Training completed — ' . $this->employee->getFullName()
                     . ($this->accountActivated ? ' (account now active)' : ''),
        );
    }

    public function content(): Content
    {
        return new Content(view: 'emails.training-completed');
    }
}
