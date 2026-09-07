@extends('emails.layout')

@section('title', 'Expected completion date reached: ' . $taskTitle)
@section('badge', 'Reminder')
@section('header_title', 'Your Expected Completion Date Is Today')

@section('greeting', 'Hello ' . $notifiable->first_name . ',')

@section('content')
<p class="message-text">
    You said you'd finish this task by <strong>{{ $dueDate }}</strong> — that's today. Update its status if it's done, partially done, or needs a new expected date.
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
        <div class="info-label">Expected completion</div>
        <div class="info-value">{{ $dueDate }}</div>
    </div>
</div>

<div class="cta-container">
    <a href="{{ $actionUrl }}" class="cta-button">Update Status</a>
</div>
@endsection
