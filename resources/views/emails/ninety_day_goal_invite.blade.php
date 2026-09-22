@extends('emails.layout')

@section('title', 'You have been added to "' . $programName . '"')
@section('badge', '90-Day Goals')
@section('header_title', 'A New 90-Day Goals Program')

@section('greeting', 'Hello ' . $notifiable->first_name . ',')

@section('content')
<p class="message-text">
    <strong>{{ $createdByName }}</strong> added you to a new 90-day goals program.
</p>

<div class="info-card">
    <div class="info-row">
        <div class="info-label">Program</div>
        <div class="info-value">{{ $programName }}</div>
    </div>
    <div class="info-row">
        <div class="info-label">Runs</div>
        <div class="info-value">{{ $startsOn }} to {{ $endsOn }}</div>
    </div>
    <div class="info-row">
        <div class="info-label">Add your customers by</div>
        <div class="info-value">{{ $deadline }}</div>
    </div>
</div>

<div class="cta-container">
    <a href="{{ $actionUrl }}" class="cta-button">Add Your Customers</a>
</div>

<p class="message-text" style="font-size: 12px; opacity: 0.8; text-align: center;">
    Pick target customers from your own Sales Pipeline and Sales Report, then give each a status every Monday.
</p>
@endsection
