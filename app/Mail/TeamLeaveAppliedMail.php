<?php

namespace App\Mail;

use App\Models\LeaveRequest;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class TeamLeaveAppliedMail extends Mailable
{
    use Queueable, SerializesModels;

    public $leaveRequest;
    public $applicant;
    public $fromDate;
    public $toDate;
    public $leaveTypeName;
    public $teamName;
    public $duration;

    /**
     * @param LeaveRequest $leaveRequest
     * @param User         $applicant    The user who applied for the leave
     */
    public function __construct(LeaveRequest $leaveRequest, User $applicant)
    {
        $this->leaveRequest  = $leaveRequest;
        $this->applicant     = $applicant;
        $this->fromDate      = $leaveRequest->from_date instanceof \Carbon\Carbon
            ? $leaveRequest->from_date->format('d M, Y')
            : \Carbon\Carbon::parse($leaveRequest->from_date)->format('d M, Y');
        $this->toDate        = $leaveRequest->to_date instanceof \Carbon\Carbon
            ? $leaveRequest->to_date->format('d M, Y')
            : \Carbon\Carbon::parse($leaveRequest->to_date)->format('d M, Y');
        $this->leaveTypeName = $leaveRequest->leaveType->name ?? 'Leave';
        $this->teamName      = $applicant->team->name ?? 'Your Team';

        // Calculate duration
        $from = \Carbon\Carbon::parse($leaveRequest->from_date);
        $to   = \Carbon\Carbon::parse($leaveRequest->to_date);
        $days = $leaveRequest->is_half_day ? 0.5 : ($from->diffInDays($to) + 1);
        $this->duration = $days == 0.5 ? 'Half Day' : ($days . ' ' . \Illuminate\Support\Str::plural('day', $days));
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: "🏖️ {$this->applicant->first_name} from {$this->teamName} has applied for leave",
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.team_leave_applied',
            with: [
                'applicantName'  => $this->applicant->getFullName(),
                'applicantCode'  => $this->applicant->code,
                'leaveTypeName'  => $this->leaveTypeName,
                'fromDate'       => $this->fromDate,
                'toDate'         => $this->toDate,
                'duration'       => $this->duration,
                'userNotes'      => $this->leaveRequest->user_notes,
                'teamName'       => $this->teamName,
                'isHalfDay'      => $this->leaveRequest->is_half_day,
                'halfDaySession' => $this->leaveRequest->half_day_session,
            ],
        );
    }
}
