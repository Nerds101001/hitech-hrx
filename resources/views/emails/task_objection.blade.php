@extends('emails.layout')

@section('title', 'Task Sent Back: ' . $taskTitle)
@section('badge', 'Action Required')
@section('header_title', 'A Task Was Sent Back')

@section('greeting', 'Hello ' . $notifiable->first_name . ',')

@section('content')
<p class="message-text">
    <strong>{{ $objectedByName }}</strong> raised an objection on this task and sent it back to you to correct.
</p>

<div class="info-card">
    <div class="info-row">
        <div class="info-label">Task</div>
        <div class="info-value">{{ $taskTitle }}</div>
    </div>
    <div class="info-row">
        <div class="info-label">Reason</div>
        <div class="info-value">{{ $objectionNotes }}</div>
    </div>
</div>

<div class="cta-container">
    <a href="{{ $actionUrl }}" class="cta-button">View My Tasks</a>
</div>

<p class="message-text" style="font-size: 12px; opacity: 0.8; text-align: center;">
    Please correct it and resubmit.
</p>
@endsection
