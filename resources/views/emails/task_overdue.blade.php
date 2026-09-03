@extends('emails.layout')

@section('title', 'Overdue: ' . $taskTitle)
@section('badge', 'Overdue')
@section('header_title', 'This Task Is Overdue')

@section('greeting', 'Hello ' . $notifiable->first_name . ',')

@section('content')
<p class="message-text">
    This task was due on <strong>{{ $dueDate }}</strong> and has not been marked complete.
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
        <div class="info-value" style="color: #dc2626;">{{ $dueDate }}</div>
    </div>
</div>

<div class="cta-container">
    <a href="{{ $actionUrl }}" class="cta-button">Mark It Complete</a>
</div>

<p class="message-text" style="font-size: 12px; opacity: 0.8; text-align: center;">
    If it is already done, mark it complete with a remark so this does not keep showing as pending.
</p>
@endsection
