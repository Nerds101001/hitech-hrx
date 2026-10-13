@extends('emails.layout')

@section('title', 'Status Update: ' . $taskTitle)
@section('badge', 'Status Update')
@section('header_title', 'A Task Status Changed')

@section('greeting', 'Hello ' . $notifiable->first_name . ',')

@section('content')
<p class="message-text">
    <strong>{{ $changedByName }}</strong> marked this task as <strong>{{ $statusLabel }}</strong>.
</p>

<div class="info-card">
    <div class="info-row">
        <div class="info-label">Task</div>
        <div class="info-value">{{ $taskTitle }}</div>
    </div>
    <div class="info-row">
        <div class="info-label">New Status</div>
        <div class="info-value">{{ $statusLabel }}</div>
    </div>
    @if($remarks)
    <div class="info-row">
        <div class="info-label">Remarks</div>
        <div class="info-value">{{ $remarks }}</div>
    </div>
    @endif
</div>

<div class="cta-container">
    <a href="{{ $actionUrl }}" class="cta-button">View My Tasks</a>
</div>
@endsection
