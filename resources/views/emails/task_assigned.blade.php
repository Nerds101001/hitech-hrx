@extends('emails.layout')

@section('title', 'New Task: ' . $taskTitle)
@section('badge', 'New Task')
@section('header_title', 'You Have a New Task')

@section('greeting', 'Hello ' . $notifiable->first_name . ',')

@section('content')
<p class="message-text">
    <strong>{{ $assignedByName }}</strong> assigned you a new task.
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
    @if($dueDate)
    <div class="info-row">
        <div class="info-label">Due</div>
        <div class="info-value">{{ $dueDate }}</div>
    </div>
    @endif
    @if($priority)
    <div class="info-row">
        <div class="info-label">Priority</div>
        <div class="info-value" style="text-transform: capitalize;">{{ $priority }}</div>
    </div>
    @endif
</div>

<div class="cta-container">
    <a href="{{ $actionUrl }}" class="cta-button">View My Tasks</a>
</div>

<p class="message-text" style="font-size: 12px; opacity: 0.8; text-align: center;">
    You can mark it complete and leave a remark once it is done.
</p>
@endsection
