<?php

namespace App\Mail;

use App\Models\User;
use App\Services\HrNotificationService;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * The joiner's ID documents (photo, cancelled cheque, Aadhaar, PAN, …) sent
 * to accounts ONLY — deliberately a separate mail from the announcement, so
 * personal documents never reach the wider recipient list.
 */
class NewJoinerDocuments extends Mailable
{
    use Queueable, SerializesModels;

    public User $joiner;
    public array $documents;

    public function __construct(User $joiner)
    {
        $this->joiner    = $joiner;
        $this->documents = HrNotificationService::onboardingDocuments($joiner);
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Documents — ' . $this->joiner->getFullName()
                . ($this->joiner->code ? ' (' . $this->joiner->code . ')' : ''),
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.hr.new_joiner_documents',
            with: [
                'joiner'    => $this->joiner,
                'documents' => $this->documents,
                'details'   => HrNotificationService::joinerDetails($this->joiner),
            ],
        );
    }

    /**
     * Attached from decrypted data rather than fromPath(): the files on disk
     * are encrypted, so attaching the path would deliver an unreadable blob.
     *
     * @return Attachment[]
     */
    public function attachments(): array
    {
        return collect($this->documents)
            ->map(fn($d) => Attachment::fromData(fn() => $d['data'], $d['name'])
                ->withMime($d['mime']))
            ->all();
    }
}
