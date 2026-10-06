@extends('emails.layout')

@section('title', 'Add your 90-Day customers')
@section('badge', 'Reminder')
@section('header_title', 'Finish Adding Your Customers')

@section('greeting', 'Hello ' . $notifiable->first_name . ',')

@section('content')
<p class="message-text">
    You have added <strong>{{ $count }}</strong> of <strong>{{ $min }}</strong> customers to your 90-Day goal.
    The window to add customers closes on <strong>{{ $closesAt }}</strong> &mdash; please add the remaining
    <strong>{{ $shortBy }}</strong> before then.
</p>

<div class="info-card">
    <div class="info-row">
        <div class="info-label">Added so far</div>
        <div class="info-value">{{ $count }} / {{ $min }}</div>
    </div>
    <div class="info-row">
        <div class="info-label">Still to add</div>
        <div class="info-value" style="color: #dc2626;">{{ $shortBy }}</div>
    </div>
    <div class="info-row">
        <div class="info-label">Window closes</div>
        <div class="info-value">{{ $closesAt }}</div>
    </div>
</div>

<div class="cta-container">
    <a href="{{ $actionUrl }}" class="cta-button">Add Customers</a>
</div>
@endsection
