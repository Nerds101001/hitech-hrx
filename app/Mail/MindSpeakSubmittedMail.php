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
        $this->entry = $entry;
        // Completely withhold identity for anonymous submissions — null is
        // passed to the view so there is no way to accidentally expose it.
        $this->submitter = $entry->is_anonymous ? null : $entry->user;
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
