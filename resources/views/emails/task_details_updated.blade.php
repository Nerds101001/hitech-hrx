@extends('emails.layout')

@section('title', 'Task Updated: ' . $taskTitle)
@section('badge', 'Task Updated')
@section('header_title', 'A Task Was Updated')

@section('greeting', 'Hello ' . $notifiable->first_name . ',')

@section('content')
<p class="message-text">
    <strong>{{ $changedByName }}</strong> made changes to a task assigned to you.
</p>

<div class="info-card">
    <div class="info-row">
        <div class="info-label">Task</div>
        <div class="info-value">{{ $taskTitle }}</div>
    </div>
    <div class="info-row">
        <div class="info-label">What changed</div>
        <div class="info-value">{{ $summary }}</div>
    </div>
</div>

<div class="cta-container">
    <a href="{{ $actionUrl }}" class="cta-button">View My Tasks</a>
</div>
@endsection
