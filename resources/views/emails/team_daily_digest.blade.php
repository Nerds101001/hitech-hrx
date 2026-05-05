@extends('emails.layout')

@section('title', 'Daily Team Digest - ' . $date)
@section('badge', 'Daily Update')
@section('header_title', 'Team Out Today')

@section('content')
<p class="message-text">
    Hello {{ $notifiable->first_name }}, here is the availability digest for <strong>{{ $teamName }}</strong> today, {{ $date }}.
</p>

<div class="info-card">
    @if($leavesToday->isEmpty())
        <div style="text-align: center; padding: 20px;">
            <div style="font-size: 24px; margin-bottom: 10px;">🌟</div>
            <div class="info-value">Full Strength!</div>
            <div class="message-text" style="margin-bottom: 0;">Everyone in your team is available today.</div>
        </div>
    @else
        <div class="info-label" style="margin-bottom: 15px;">Team Members on Leave</div>
        @foreach($leavesToday as $leave)
            <div class="info-row">
                <div class="info-value" style="display: flex; justify-content: space-between; align-items: center;">
                    <span>{{ $leave->user->getFullName() }}</span>
                    <span style="font-size: 11px; font-weight: normal; color: #64748b;">{{ $leave->leaveType->name }}</span>
                </div>
            </div>
        @endforeach
    @endif
</div>

<div class="cta-container">
    <a href="{{ url('/') }}" class="cta-button">Open Portal</a>
</div>

<p class="message-text" style="font-size: 12px; opacity: 0.8; text-align: center;">
    Wishing you a productive day ahead!
</p>
@endsection
