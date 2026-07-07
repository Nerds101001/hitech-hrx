@extends('emails.layout')

@section('title', $applicantName . ' has applied for leave')
@section('badge', 'Team Leave Notice')
@section('header_title', 'Teammate on Leave')

@section('content')
<p class="message-text">
    Hi <strong>{{ $teamName }}</strong> team, just a heads-up — one of your team members has submitted a leave request.
</p>

{{-- Employee Card --}}
<div class="info-card" style="background: #f8fafc; border-left: 4px solid #00796b; padding: 20px 24px; margin-bottom: 20px;">
    <div style="display: flex; align-items: center; margin-bottom: 16px;">
        <div style="width: 44px; height: 44px; background: linear-gradient(135deg, #00796b, #009688); border-radius: 50%; display: flex; align-items: center; justify-content: center; color: #fff; font-weight: 700; font-size: 18px; margin-right: 14px; flex-shrink: 0;">
            {{ strtoupper(substr($applicantName, 0, 1)) }}
        </div>
        <div>
            <div style="font-size: 16px; font-weight: 700; color: #1e293b;">{{ $applicantName }}</div>
            <div style="font-size: 12px; color: #64748b; margin-top: 2px;">{{ $applicantCode }} &bull; {{ $teamName }}</div>
        </div>
    </div>

    <table style="width: 100%; border-collapse: collapse;">
        <tr>
            <td style="padding: 8px 0; font-size: 13px; color: #64748b; width: 35%;">Leave Type</td>
            <td style="padding: 8px 0; font-size: 13px; color: #1e293b; font-weight: 600;">
                {{ $leaveTypeName }}
                @if($isHalfDay)
                    <span style="font-size: 11px; background: #fef3c7; color: #92400e; padding: 2px 7px; border-radius: 10px; margin-left: 6px;">
                        Half Day ({{ ucfirst(str_replace('_', ' ', $halfDaySession ?? 'first_half')) }})
                    </span>
                @endif
            </td>
        </tr>
        <tr>
            <td style="padding: 8px 0; font-size: 13px; color: #64748b;">From</td>
            <td style="padding: 8px 0; font-size: 13px; color: #1e293b; font-weight: 600;">{{ $fromDate }}</td>
        </tr>
        <tr>
            <td style="padding: 8px 0; font-size: 13px; color: #64748b;">To</td>
            <td style="padding: 8px 0; font-size: 13px; color: #1e293b; font-weight: 600;">{{ $toDate }}</td>
        </tr>
        <tr>
            <td style="padding: 8px 0; font-size: 13px; color: #64748b;">Duration</td>
            <td style="padding: 8px 0; font-size: 13px; color: #1e293b; font-weight: 600;">{{ $duration }}</td>
        </tr>
        @if($userNotes)
        <tr>
            <td style="padding: 8px 0; font-size: 13px; color: #64748b; vertical-align: top;">Reason</td>
            <td style="padding: 8px 0; font-size: 13px; color: #475569; font-style: italic;">{{ $userNotes }}</td>
        </tr>
        @endif
    </table>
</div>

<div style="background: #fffbeb; border: 1px solid #fde68a; border-radius: 8px; padding: 14px 18px; margin-bottom: 20px;">
    <p style="margin: 0; font-size: 13px; color: #78350f;">
        ⚠️ <strong>Note:</strong> This leave request is pending approval. Plan your work accordingly.
    </p>
</div>

<div class="cta-container">
    <a href="{{ url('/leaveRequests') }}" class="cta-button">View Leave Calendar</a>
</div>

<p class="message-text" style="font-size: 12px; opacity: 0.7; text-align: center; margin-top: 16px;">
    You received this email because you are a member of the <strong>{{ $teamName }}</strong> team.
</p>
@endsection
