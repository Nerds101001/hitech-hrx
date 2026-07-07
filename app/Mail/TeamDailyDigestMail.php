<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use Carbon\Carbon;

class TeamDailyDigestMail extends Mailable
{
    use Queueable, SerializesModels;

    public $leavesToday;
    public $teamName;
    public $date;

    /**
     * Create a new message instance.
     *
     * @param \Illuminate\Support\Collection $leavesToday  Collection of LeaveRequest models with user.leaveType
     * @param string                         $teamName
     */
    public function __construct($leavesToday, string $teamName)
    {
        $this->leavesToday = $leavesToday;
        $this->teamName    = $teamName;
        $this->date        = Carbon::today()->format('d M, Y');
    }

    /**
     * Get the message envelope.
     */
    public function envelope(): Envelope
    {
        return new Envelope(
            subject: "Team Availability Digest — {$this->date} | {$this->teamName}",
        );
    }

    /**
     * Get the message content definition.
     */
    public function content(): Content
    {
        return new Content(
            view: 'emails.team_daily_digest',
            with: [
                'leavesToday' => $this->leavesToday,
                'teamName'    => $this->teamName,
                'date'        => $this->date,
            ],
        );
    }
}
