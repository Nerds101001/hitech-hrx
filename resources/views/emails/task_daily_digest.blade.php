@extends('emails.layout')

@section('title', 'Your Daily Task Digest')
@section('badge', now()->format('d M Y'))
@section('header_title', 'Your Daily Task Digest')

@section('greeting', 'Hello ' . $notifiable->first_name . ',')

@section('style')
<style>
  .sec-title { font-size: 13px; font-weight: 800; color: #0f172a; text-transform: uppercase; letter-spacing: 0.5px; margin: 28px 0 10px; }
  .sec-title:first-child { margin-top: 0; }
  .stat-row { display: table; width: 100%; margin-bottom: 14px; }
  .stat-cell { display: table-cell; text-align: center; padding: 10px 4px; background: #f8fafc; border-radius: 10px; }
  .stat-num { font-size: 20px; font-weight: 800; color: #005a5a; }
  .stat-num.danger { color: #b42318; }
  .stat-label { font-size: 10px; color: #64748b; text-transform: uppercase; letter-spacing: .5px; margin-top: 2px; }
  .mini-row { padding: 8px 0; border-bottom: 1px solid #f1f5f9; font-size: 13px; color: #334155; }
  .mini-row:last-child { border-bottom: none; }
  .mini-title { font-weight: 700; color: #1e293b; }
  .mini-meta { color: #94a3b8; font-size: 11px; }
  .pill-days { display: inline-block; background: #fef3c7; color: #92400e; font-size: 10px; font-weight: 700; border-radius: 20px; padding: 1px 8px; margin-left: 4px; }
</style>
@endsection

@section('content')
<p class="message-text">Here's everything waiting on your tasks today, in one place.</p>

<div class="sec-title">Assigned to you</div>
<div class="stat-row">
  <div class="stat-cell"><div class="stat-num {{ $assignedToYou['overdue'] > 0 ? 'danger' : '' }}">{{ $assignedToYou['overdue'] }}</div><div class="stat-label">Overdue</div></div>
  <div class="stat-cell"><div class="stat-num">{{ $assignedToYou['dueToday'] }}</div><div class="stat-label">Due Today</div></div>
  <div class="stat-cell"><div class="stat-num">{{ $assignedToYou['totalPending'] }}</div><div class="stat-label">Total Pending</div></div>
</div>

<div class="sec-title">Tasks you assigned — awaiting your reply</div>
<div class="stat-row">
  <div class="stat-cell"><div class="stat-num {{ $assignedByYou['needingReplyToday'] > 0 ? 'danger' : '' }}">{{ $assignedByYou['needingReplyToday'] }}</div><div class="stat-label">Need Reply Today</div></div>
  <div class="stat-cell"><div class="stat-num">{{ $assignedByYou['totalPendingReply'] }}</div><div class="stat-label">Total Pending Reply</div></div>
  <div class="stat-cell"><div class="stat-num">{{ $assignedByYou['repliesTodayCount'] }}</div><div class="stat-label">Replies Today</div></div>
</div>

@if(count($assignedByYou['pendingReplyList']))
<div class="info-card">
  @foreach($assignedByYou['pendingReplyList'] as $row)
    <div class="mini-row">
      <span class="mini-title">{{ $row['title'] }}</span>
      <span class="pill-days">{{ $row['daysPending'] }} {{ \Illuminate\Support\Str::plural('day', $row['daysPending']) }} pending</span>
      <div class="mini-meta">{{ $row['assignee'] }}@if($row['dueLabel']) &middot; due {{ $row['dueLabel'] }}@endif</div>
    </div>
  @endforeach
  @if($assignedByYou['pendingReplyMore'] > 0)
    <div class="mini-row mini-meta">+{{ $assignedByYou['pendingReplyMore'] }} more — see Task Management</div>
  @endif
</div>
@endif

@if(count($assignedByYou['repliesToday']))
<div class="sec-title">Replies received today</div>
<div class="info-card">
  @foreach($assignedByYou['repliesToday'] as $row)
    <div class="mini-row">
      <span class="mini-title">{{ $row['title'] }}</span>
      <div class="mini-meta">{{ $row['by'] }} at {{ $row['time'] }}</div>
      <div>{{ \Illuminate\Support\Str::limit($row['comment'], 140) }}</div>
    </div>
  @endforeach
  @if($assignedByYou['repliesTodayMore'] > 0)
    <div class="mini-row mini-meta">+{{ $assignedByYou['repliesTodayMore'] }} more — see Task Management</div>
  @endif
</div>
@endif

<div class="sec-title">Tasks you're CC'd on</div>
<div class="stat-row">
  <div class="stat-cell"><div class="stat-num {{ $ccTasks['needingUpdate'] > 0 ? 'danger' : '' }}">{{ $ccTasks['needingUpdate'] }}</div><div class="stat-label">Need an Update</div></div>
  <div class="stat-cell"><div class="stat-num">{{ $ccTasks['totalPending'] }}</div><div class="stat-label">Total Pending</div></div>
</div>

@if(count($ccTasks['pendingList']))
<div class="info-card">
  @foreach($ccTasks['pendingList'] as $row)
    <div class="mini-row">
      <span class="mini-title">{{ $row['title'] }}</span>
      <span class="pill-days">{{ $row['daysPending'] }} {{ \Illuminate\Support\Str::plural('day', $row['daysPending']) }} pending</span>
      <div class="mini-meta">{{ $row['assignee'] }}@if($row['dueLabel']) &middot; due {{ $row['dueLabel'] }}@endif</div>
    </div>
  @endforeach
  @if($ccTasks['pendingMore'] > 0)
    <div class="mini-row mini-meta">+{{ $ccTasks['pendingMore'] }} more — see Task Management</div>
  @endif
</div>
@endif

<div class="cta-container">
  <a href="{{ $actionUrl }}" class="cta-button">My Tasks</a>
</div>

<p class="message-text" style="font-size: 12px; opacity: 0.8; text-align: center;">
  One email a day — no separate email for every reply or update.
</p>
@endsection
