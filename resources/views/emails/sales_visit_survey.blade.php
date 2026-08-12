@extends('emails.layout')

@section('title', 'How did we do?')
@section('badge', 'Client Feedback')
@section('header_title', 'How Did We Do?')

@section('greeting', 'Dear ' . ($visit->client->contact_person ?: $visit->client->name) . ',')

@section('content')
<p class="message-text">
    Our sales representative, <strong>{{ $visit->salesperson->name }}</strong>, recently
    visited you regarding the scheduled
    <strong>{{ str_replace('_', ' ', $visit->visit_type) }}</strong>. We are committed to
    providing the highest quality of service, and would appreciate a brief moment of your
    time to rate the experience.
</p>

<div class="info-card">
    <div class="info-row">
        <div class="info-label">Visit Type</div>
        <div class="info-value">{{ ucwords(str_replace('_', ' ', $visit->visit_type)) }}</div>
    </div>
    <div class="info-row">
        <div class="info-label">Representative</div>
        <div class="info-value">{{ $visit->salesperson->name }}</div>
    </div>
    <div class="info-row">
        <div class="info-label">Completed On</div>
        <div class="info-value">
            {{ $visit->completed_at ? $visit->completed_at->format('l, d M Y') : '—' }}
        </div>
    </div>
</div>

<p class="message-text">
    Rate the visit on a five-point scale — it takes a few seconds, and it helps us serve
    you better.
</p>

<div class="cta-container">
    <a href="{{ route('sales-visits.survey', $visit->survey_token) }}" target="_blank" class="cta-button">
        Rate Our Visit
    </a>
</div>
@endsection
