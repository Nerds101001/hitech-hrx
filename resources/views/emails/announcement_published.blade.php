@extends('emails.layout')

@section('title', $title)
@section('badge', 'Company Announcement')
@section('header_title', 'New Announcement')

@section('style')
<style>
  .chips { margin-bottom: 16px; }
  .chip {
    display: inline-block; font-size: 10.5px; font-weight: 800;
    letter-spacing: .8px; text-transform: uppercase;
    border-radius: 50px; padding: 4px 12px; margin-right: 6px;
  }
  .chip-type { background: #e0f2f1; color: #00695c; }
  .chip-high, .chip-urgent { background: #fee2e2; color: #b91c1c; }
  .chip-medium { background: #fef3c7; color: #b45309; }
  .chip-low    { background: #e2e8f0; color: #475569; }
  .ann-title { font-size: 20px; font-weight: 800; color: #0f172a; line-height: 1.35; margin-bottom: 8px; }
  .ann-meta  { font-size: 12px; color: #94a3b8; margin-bottom: 20px; }
  .ann-body  { font-size: 14.5px; color: #475569; line-height: 1.8; white-space: pre-line; }
  .ack-note  {
    background: #fffbeb; border: 1px solid #fde68a; border-radius: 14px;
    padding: 16px 18px; margin-top: 22px; font-size: 13.5px; color: #92400e; line-height: 1.7;
  }
</style>
@endsection

@section('content')
<div class="chips">
    <span class="chip chip-type">{{ $type }}</span>
    <span class="chip chip-{{ strtolower($priority) }}">{{ $priority }} priority</span>
</div>

<div class="ann-title">{{ $title }}</div>

<div class="ann-meta">
    Posted by {{ $author }}
    @if($startDate) &middot; {{ $startDate }} @endif
    @if($endDate) &ndash; {{ $endDate }} @endif
</div>

<div class="ann-body">
    @if($isHtml ?? false)
        {!! $content !!}
    @else
        {{ $content }}
    @endif
</div>

@if($needsAck)
<div class="ack-note">
    <strong>Acknowledgement required.</strong>
    Please open the portal and confirm you have read this announcement.
</div>
@endif

<div class="cta-container">
    <a href="{{ $actionUrl }}" class="cta-button">View in Portal</a>
</div>

<p style="font-size: 11.5px; color: #cbd5e1; text-align: center; line-height: 1.7;">
    You are receiving this because it applies to your department, location or role.
</p>
@endsection
