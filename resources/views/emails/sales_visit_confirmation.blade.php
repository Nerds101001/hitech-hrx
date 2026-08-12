@extends('emails.layout')

@section('title', 'Visit Confirmation - ' . $visit->client->name)
@section('badge', strtoupper(str_replace('_', ' ', $visit->visit_type)) . ' Confirmation')
@section('header_title', 'Visit Scheduled')

@section('greeting')
    @if($recipientType === 'salesperson')
        Hello {{ $visit->salesperson->first_name }},
    @else
        Dear {{ $visit->client->contact_person ?: $visit->client->name }},
    @endif
@endsection

@section('content')
<p class="message-text">
    @if($recipientType === 'salesperson')
        A new client visit has been assigned to you by
        <strong>{{ $visit->ccAgent->first_name }} {{ $visit->ccAgent->last_name }}</strong>.
        Please review the details below and make sure to be on time.
    @else
        Thank you for your interest. Our sales representative will visit you at the
        scheduled time. The details are below.
    @endif
</p>

<div class="info-card">
    <div class="info-row">
        <div class="info-label">Client</div>
        <div class="info-value">{{ $visit->client->name }}</div>
    </div>
    <div class="info-row">
        <div class="info-label">Visit Type</div>
        <div class="info-value">{{ str_replace('_', ' ', ucwords($visit->visit_type)) }}</div>
    </div>
    <div class="info-row">
        <div class="info-label">Scheduled</div>
        <div class="info-value">
            {{ $visit->scheduled_at->format('l, d M Y') }} at {{ $visit->scheduled_at->format('h:i A') }}
        </div>
    </div>
    @if($visit->client->address)
    <div class="info-row">
        <div class="info-label">Address</div>
        <div class="info-value">
            {{ $visit->client->address }}{{ $visit->client->city ? ', ' . $visit->client->city : '' }}
        </div>
    </div>
    @endif
    @if($visit->client->phone)
    <div class="info-row">
        <div class="info-label">Contact No.</div>
        <div class="info-value">{{ $visit->client->phone }}</div>
    </div>
    @endif
    @if($visit->notes)
    <div class="info-row">
        <div class="info-label">Notes</div>
        <div class="info-value" style="font-weight: 400; color: #64748b;">{{ $visit->notes }}</div>
    </div>
    @endif
</div>

@if($recipientType === 'salesperson')
<p class="message-text" style="margin-bottom: 0;">
    Please log your GPS location and submit photo proof once the visit is complete,
    from your HRX dashboard.
</p>
@endif
@endsection
