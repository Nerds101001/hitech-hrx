@extends('emails.layout')

@section('title', 'Escalation: ' . $taskTitle)
@section('badge', 'Escalation')
@section('header_title', 'An Overdue Task In Your Team')

@section('greeting', 'Hello ' . $notifiable->first_name . ',')

@section('content')
<p class="message-text">
    A task assigned to <strong>{{ $assigneeName }}</strong> is
    <strong>{{ $daysOverdue }} day{{ $daysOverdue === 1 ? '' : 's' }}</strong>
    overdue and has not been completed.
</p>

<div class="info-card">
    <div class="info-row">
        <div class="info-label">Task</div>
        <div class="info-value">{{ $taskTitle }}</div>
    </div>
    @if($description)
    <div class="info-row">
        <div class="info-label">Details</div>
        <div class="info-value">{{ $description }}</div>
    </div>
    @endif
    <div class="info-row">
        <div class="info-label">Assigned to</div>
        <div class="info-value">{{ $assigneeName }}</div>
    </div>
    <div class="info-row">
        <div class="info-label">Was due</div>
        <div class="info-value" style="color: #dc2626;">{{ $dueDate }}</div>
    </div>
</div>

<div class="cta-container">
    <a href="{{ $actionUrl }}" class="cta-button">Review Their Tasks</a>
</div>
@endsection
