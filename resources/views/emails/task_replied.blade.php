@extends('emails.layout')

@section('title', 'New Reply: ' . $taskTitle)
@section('badge', 'New Reply')
@section('header_title', 'Someone Replied On Your Task')

@section('greeting', 'Hello ' . $notifiable->first_name . ',')

@section('content')
<p class="message-text">
    <strong>{{ $repliedByName }}</strong> replied on a task you're involved in.
</p>

<div class="info-card">
    <div class="info-row">
        <div class="info-label">Task</div>
        <div class="info-value">{{ $taskTitle }}</div>
    </div>
    <div class="info-row">
        <div class="info-label">Reply</div>
        <div class="info-value">{{ $comment }}</div>
    </div>
</div>

<div class="cta-container">
    <a href="{{ $actionUrl }}" class="cta-button">View My Tasks</a>
</div>
@endsection
