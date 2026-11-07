<?php

namespace App\Mail;

use App\Models\MindSpeak;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class MindSpeakSubmittedMail extends Mailable
{
    use Queueable, SerializesModels;

    public MindSpeak $entry;
    public ?User $submitter;

    public function __construct(MindSpeak $entry)
    {
        $this->entry     = $entry;
        // Eager-load so the view can use it without an extra query.
        // Anonymous entries intentionally still expose the User model to the
        // reviewer — they need to know who filed what. is_anonymous only hides
        // identity from peers, not from HR/reviewer.
        $this->submitter = $entry->user;
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: '[Mind Speak] New ' . $this->entry->category . ' submission',
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.hr.mind_speak_submitted',
            with: [
                'entry'     => $this->entry,
                'submitter' => $this->submitter,
            ],
        );
    }
}
