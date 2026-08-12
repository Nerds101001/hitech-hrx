@extends('emails.layout')

@section('title', 'Policy Acknowledged - ' . $policyTitle)
@section('badge', 'HR Policy')
@section('header_title', 'Acknowledgement Recorded')

@section('content')
<p class="message-text">
    Hello {{ $userName }}, this confirms that you have read and acknowledged
    <strong>{{ $policyTitle }}</strong>. A signed receipt is attached to this email
    for your records.
</p>

<div class="info-card">
    <div class="info-row">
        <div class="info-label">Policy</div>
        <div class="info-value">{{ $policyTitle }}</div>
    </div>
    @if($policyCategory)
    <div class="info-row">
        <div class="info-label">Category</div>
        <div class="info-value">{{ $policyCategory }}</div>
    </div>
    @endif
    <div class="info-row">
        <div class="info-label">Acknowledged On</div>
        <div class="info-value">{{ $acknowledgedAt }}</div>
    </div>
    <div class="info-row">
        <div class="info-label">Recorded From</div>
        <div class="info-value">{{ $ipAddress ?: 'Not recorded' }}</div>
    </div>
    @if($signatureHash)
    <div class="info-row">
        <div class="info-label">Receipt Reference</div>
        <div class="info-value" style="font-family: monospace; font-size: 11px;">{{ $signatureHash }}</div>
    </div>
    @endif
</div>

<p class="message-text" style="margin-top: 18px;">
    Keep the attached receipt safe — it is your proof of acknowledgement. No further
    action is needed from you.
</p>
@endsection
