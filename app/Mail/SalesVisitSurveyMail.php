<?php
namespace App\Mail;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use App\Models\SalesVisit;

class SalesVisitSurveyMail extends Mailable {
    use Queueable, SerializesModels;
    public function __construct(public SalesVisit $visit) {}

    public function envelope(): Envelope {
        return new Envelope(subject: 'How was your visit? Rate your experience - ' . ($this->visit->client->name ?? 'HRX'));
    }

    public function content(): Content {
        return new Content(view: 'emails.sales_visit_survey', with: [
            'visit' => $this->visit,
        ]);
    }
}
