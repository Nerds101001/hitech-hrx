<?php

namespace App\Mail;

use App\Models\EmployeeComplaint;
use App\Models\EmployeeWarning;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Sent to the employee it concerns, HR, and their reporting manager the
 * moment a warning or complaint is recorded against them.
 */
class EmployeeLifecycleNoticeAlert extends Mailable
{
    use Queueable, SerializesModels;

    public EmployeeWarning|EmployeeComplaint $notice;
    public string $kind; // 'warning' | 'complaint'
    public User $employee;
    public ?User $issuedBy;

    public function __construct(EmployeeWarning|EmployeeComplaint $notice, string $kind)
    {
        $this->notice   = $notice;
        $this->kind     = $kind;
        $this->employee = $notice->user;
        $this->issuedBy = $kind === 'warning' ? $notice->givenBy : $notice->complainant;
    }

    public function envelope(): Envelope
    {
        $word = $this->kind === 'warning' ? 'Warning' : 'Complaint';

        return new Envelope(
            subject: "{$word} recorded — {$this->employee->getFullName()}",
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.hr.lifecycle_notice',
            with: [
                'notice'   => $this->notice,
                'kind'     => $this->kind,
                'employee' => $this->employee,
                'issuedBy' => $this->issuedBy,
            ],
        );
    }
}
