@extends('emails.layout')

@section('title', 'New Leave Application - ' . $employeeName)
@section('badge', 'Action Required')
@section('header_title', 'New Leave Request')

@section('content')
<p class="message-text">
    Hello {{ $notifiable->first_name }}, you have received a new leave application from <strong>{{ $employeeName }}</strong> ({{ $employeeCode }}).
</p>

<div class="info-card">
    <div class="info-row">
        <div class="info-label">Employee</div>
        <div class="info-value">{{ $employeeName }}</div>
    </div>
    <div class="info-row">
        <div class="info-label">Leave Type</div>
        <div class="info-value">{{ $leaveType }} @if($isBackdated) <span style="color: #d97706; font-size: 10px;">(BACK DATED)</span> @endif</div>
    </div>
    <div class="info-row">
        <div class="info-label">Period</div>
        <div class="info-value">{{ $duration }} Days ({{ $fromDate }} - {{ $toDate }})</div>
    </div>
    <div class="info-row">
        <div class="info-label">Employee Reason</div>
        <div class="info-value">{{ $userNotes ?: 'No notes provided.' }}</div>
    </div>
</div>

<div class="cta-container">
    <a href="{{ url('/leaveRequests') }}" class="cta-button">Review Application</a>
</div>

<p class="message-text" style="font-size: 12px; opacity: 0.8; text-align: center;">
    Please log in to the HRX portal to approve or reject this request.
</p>
@endsection
