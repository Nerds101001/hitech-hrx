@extends('emails.layout')

@section('title', 'Leave Request Update - ' . $statusText)
@section('badge', 'Leave Management')
@section('header_title', 'Request ' . $statusText)

@section('content')
<p class="message-text">
    Hello {{ $notifiable->first_name }}, your leave request for <strong>{{ $leaveType }}</strong> has been <strong>{{ $status }}</strong>.
</p>

<div class="info-card">
    <div class="info-row">
        <div class="info-label">Leave Type</div>
        <div class="info-value">{{ $leaveType }} @if($isBackdated) <span style="color: #d97706; font-size: 10px;">(BACK DATED)</span> @endif</div>
    </div>
    <div class="info-row">
        <div class="info-label">Duration</div>
        <div class="info-value">{{ $duration }} Days ({{ $fromDate }} - {{ $toDate }})</div>
    </div>
    <div class="info-row">
        <div class="info-label">Decision Remarks</div>
        <div class="info-value">{{ $adminNotes ?: 'No specific notes provided.' }}</div>
    </div>
</div>

<div class="cta-container">
    <a href="{{ url('/user/leaves') }}" class="cta-button">View My Leaves</a>
</div>

<p class="message-text" style="font-size: 12px; opacity: 0.8; text-align: center;">
    If you have any questions regarding this decision, please contact your reporting manager or HR operations.
</p>
@endsection
