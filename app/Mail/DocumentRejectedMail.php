<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use App\Models\User;

class DocumentRejectedMail extends Mailable
{
    use Queueable, SerializesModels;

    /**
     * @param User   $employee        The employee whose document was rejected
     * @param string $documentName    Human-readable document name (e.g. "Aadhaar Card")
     * @param string $adminRemarks    Reason / remarks from the admin/HR
     * @param string $reviewerName    Name of the admin/HR who rejected
     */
    public function __construct(
        public User   $employee,
        public string $documentName,
        public string $adminRemarks,
        public string $reviewerName = 'HR Team'
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Action Required: Document Rejected — ' . $this->documentName
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.document_rejected',
            with: [
                'employee'     => $this->employee,
                'documentName' => $this->documentName,
                'adminRemarks' => $this->adminRemarks,
                'reviewerName' => $this->reviewerName,
            ]
        );
    }
}
