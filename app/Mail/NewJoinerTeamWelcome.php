<?php

namespace App\Mail;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Sent to the joiner's own teammates only (same team_id) — a lighter, warmer
 * note than NewJoinerAnnouncement, which carries internal detail (accounts/
 * HR/manager action items) that a teammate has no reason to see.
 */
class NewJoinerTeamWelcome extends Mailable
{
    use Queueable, SerializesModels;

    public User $joiner;

    public function __construct(User $joiner)
    {
        $this->joiner = $joiner;
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Meet your new teammate — ' . $this->joiner->getFullName(),
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.hr.new_joiner_team_welcome',
            with: [
                'joiner'     => $this->joiner,
                'designation' => optional($this->joiner->designation)->name,
                'team'       => optional($this->joiner->team)->name,
            ],
        );
    }
}
