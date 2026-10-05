@extends('emails.layout')

@section('title', 'Due soon: ' . $taskTitle)
@section('badge', 'Reminder')
@section('header_title', 'A Task Is Due Soon')

@section('greeting', 'Hello ' . $notifiable->first_name . ',')

@section('content')
<p class="message-text">
    This is a reminder that the task below is due on <strong>{{ $dueDate }}</strong>.
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
        <div class="info-label">Due</div>
        <div class="info-value">{{ $dueDate }}</div>
    </div>
</div>

<div class="cta-container">
    <a href="{{ $actionUrl }}" class="cta-button">Open My Tasks</a>
</div>
@endsection
