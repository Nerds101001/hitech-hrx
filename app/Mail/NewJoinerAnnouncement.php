<?php

namespace App\Mail;

use App\Models\User;
use App\Services\HrNotificationService;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Sent once, to everyone at the same time, when HR approves an onboarding.
 * Accounts and the reporting manager are on To; HR is copied — so the whole
 * thread is visible to every recipient rather than being split into separate
 * mails.
 */
class NewJoinerAnnouncement extends Mailable
{
    use Queueable, SerializesModels;

    public User $joiner;
    public array $details;

    public function __construct(User $joiner)
    {
        $this->joiner  = $joiner;
        $this->details = HrNotificationService::joinerDetails($joiner);
    }

    public function envelope(): Envelope
    {
        $name = $this->joiner->getFullName();
        $dept = optional($this->joiner->department)->name;

        return new Envelope(
            subject: 'New Joiner — ' . $name . ($dept ? ' (' . $dept . ')' : ''),
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.hr.new_joiner',
            with: [
                'joiner'  => $this->joiner,
                'details' => $this->details,
            ],
        );
    }
}
