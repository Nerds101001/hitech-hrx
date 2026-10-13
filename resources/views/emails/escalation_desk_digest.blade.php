@extends('emails.layout')

@section('title', 'Escalation Desk Digest')
@section('badge', now()->format('d M Y'))
@section('header_title', $count . ' Task' . ($count === 1 ? '' : 's') . ' Escalated Today')

@section('greeting', 'Hello,')

@section('style')
<style>
  .mini-row { padding: 10px 0; border-bottom: 1px solid #f1f5f9; font-size: 13px; color: #334155; }
  .mini-row:last-child { border-bottom: none; }
  .mini-title { font-weight: 700; color: #1e293b; }
  .mini-meta { color: #64748b; font-size: 12px; margin-top: 2px; }
  .pill-days { display: inline-block; background: #fee2e2; color: #b42318; font-size: 10px; font-weight: 700; border-radius: 20px; padding: 2px 9px; margin-left: 6px; }
</style>
@endsection

@section('content')
<p class="message-text">
    Every task that crossed the escalation threshold in this run — one email instead of one per manager per task.
</p>

<div class="info-card">
  @foreach($escalations as $e)
    <div class="mini-row">
      <span class="mini-title">{{ $e['taskTitle'] }}</span>
      <span class="pill-days">{{ $e['daysOverdue'] }} {{ \Illuminate\Support\Str::plural('day', $e['daysOverdue']) }} overdue</span>
      <div class="mini-meta">
        Assigned to {{ $e['assigneeName'] }} &middot; Manager: {{ $e['managerName'] }}
        @if($e['dueLabel']) &middot; was due {{ $e['dueLabel'] }} @endif
      </div>
    </div>
  @endforeach
</div>

<div class="cta-container">
  <a href="{{ $actionUrl }}" class="cta-button">Open Task Management</a>
</div>

<p class="message-text" style="font-size: 12px; opacity: 0.8; text-align: center;">
  Each manager above was also notified in-app for their own report — this is the one consolidated email for the whole run.
</p>
@endsection
