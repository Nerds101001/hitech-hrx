<?php

namespace App\Mail;

use App\Models\ExitClearance;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Sent when HR logs an exit, and again whenever a department signs off, so
 * everyone can see what clearance is still outstanding.
 */
class ExitClearanceAlert extends Mailable
{
    use Queueable, SerializesModels;

    public User $leaver;
    public string $exitType;
    public int $exitId;
    public $clearances;
    public array $progress;

    public function __construct(User $leaver, string $exitType, int $exitId)
    {
        $this->leaver     = $leaver;
        $this->exitType   = $exitType;
        $this->exitId     = $exitId;
        $this->clearances = ExitClearance::forExit($exitType, $exitId)
            ->with('clearedBy')->orderBy('id')->get();
        $this->progress   = ExitClearance::progress($exitType, $exitId);
    }

    public function envelope(): Envelope
    {
        $word = $this->exitType === 'termination' ? 'Termination' : 'Resignation';
        $tag  = $this->progress['done'] ? 'All clearances received' : $this->progress['label'];

        return new Envelope(
            subject: "{$word} — {$this->leaver->getFullName()} ({$tag})",
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.hr.exit_clearance',
            with: [
                'leaver'     => $this->leaver,
                'exitType'   => $this->exitType,
                'clearances' => $this->clearances,
                'progress'   => $this->progress,
            ],
        );
    }
}
