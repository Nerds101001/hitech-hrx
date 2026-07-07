@extends('emails.layout')

@section('title', 'Daily Team Digest - ' . $date)
@section('badge', 'Daily Update')
@section('header_title', 'Team Out Today')

@section('content')
<p class="message-text">
    Hello <strong>{{ $teamName }}</strong> team, here is your availability digest for today, <strong>{{ $date }}</strong>.
</p>

<div class="info-card">
    @if($leavesToday->isEmpty())
        <div style="text-align: center; padding: 20px;">
            <div style="font-size: 28px; margin-bottom: 10px;">🌟</div>
            <div class="info-value">Full Strength!</div>
            <div class="message-text" style="margin-bottom: 0;">Everyone in your team is available today.</div>
        </div>
    @else
        <div class="info-label" style="margin-bottom: 15px;">
            {{ $leavesToday->count() }} {{ Str::plural('member', $leavesToday->count()) }} out today
        </div>
        @foreach($leavesToday as $leave)
            <div class="info-row" style="padding: 12px 0; border-bottom: 1px solid #f1f5f9;">
                <div class="info-value" style="display: flex; justify-content: space-between; align-items: center;">
                    <span style="font-weight: 600;">{{ $leave->user->getFullName() }}</span>
                    <span style="font-size: 11px; font-weight: normal; color: #64748b; background: #f1f5f9; padding: 3px 8px; border-radius: 20px;">
                        {{ $leave->leaveType->name ?? 'Leave' }}
                    </span>
                </div>
                <div style="font-size: 12px; color: #94a3b8; margin-top: 4px;">
                    {{ \Carbon\Carbon::parse($leave->from_date)->format('d M') }}
                    @if($leave->from_date != $leave->to_date)
                        → {{ \Carbon\Carbon::parse($leave->to_date)->format('d M') }}
                    @endif
                </div>
            </div>
        @endforeach
    @endif
</div>

<div class="cta-container">
    <a href="{{ url('/') }}" class="cta-button">Open Portal</a>
</div>

<p class="message-text" style="font-size: 12px; opacity: 0.8; text-align: center;">
    Plan accordingly and have a productive day! 🚀
</p>
@endsection
