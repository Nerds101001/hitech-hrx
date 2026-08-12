@extends('emails.layout')

@section('title', 'Probation Evaluation - ' . $employee->name)
@section('badge', 'Action Required')
@section('header_title', 'Probation Evaluation Task')

@section('greeting', 'Hi ' . $manager->first_name . ',')

@section('content')
<p class="message-text">
    The probation period for <strong>{{ $employee->name }}</strong> has concluded today.
    As their reporting manager, your feedback determines their employment status —
    please complete the performance evaluation form using the link below.
</p>

<div class="info-card">
    <div class="info-row">
        <div class="info-label">Employee Name</div>
        <div class="info-value">{{ $employee->name }}</div>
    </div>
    <div class="info-row">
        <div class="info-label">Employee Code</div>
        <div class="info-value">{{ $employee->code }}</div>
    </div>
    <div class="info-row">
        <div class="info-label">Designation</div>
        <div class="info-value">{{ $employee->designation?->name ?? 'N/A' }}</div>
    </div>
    <div class="info-row">
        <div class="info-label">Joining Date</div>
        <div class="info-value">{{ \Carbon\Carbon::parse($employee->date_of_joining)->format('d M, Y') }}</div>
    </div>
</div>

<div class="cta-container">
    <a href="{{ $url }}" class="cta-button">Start Evaluation Form</a>
</div>

<p style="font-size: 13px; color: #94a3b8; line-height: 1.7;">
    Your evaluation is required before HR can confirm employment. Please make sure every
    section is filled in accurately.
</p>
@endsection
