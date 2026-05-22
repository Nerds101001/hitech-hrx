<?php
namespace App\Mail;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use App\Models\SalesVisit;

class SalesVisitConfirmation extends Mailable {
    use Queueable, SerializesModels;
    public function __construct(public SalesVisit $visit, public string $recipientType = 'salesperson') {}

    public function envelope(): Envelope {
        $subject = match($this->recipientType) {
            'salesperson' => 'New Visit Assigned: ' . $this->visit->client->name . ' on ' . $this->visit->scheduled_at->format('d M Y, h:i A'),
            'client' => 'Visit Scheduled: Our Team is Coming on ' . $this->visit->scheduled_at->format('d M Y'),
            default => 'Sales Visit Confirmation',
        };
        return new Envelope(subject: $subject);
    }

    public function content(): Content {
        return new Content(view: 'emails.sales_visit_confirmation', with: [
            'visit' => $this->visit,
            'recipientType' => $this->recipientType,
        ]);
    }
}
