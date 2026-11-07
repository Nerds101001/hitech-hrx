@extends('emails.layout')

@section('title', 'New Mind Speak — ' . $entry->category)
@section('badge', 'New Submission')
@section('header_title', 'Mind Speak: ' . $entry->category)
@section('greeting', 'Hi Mukul,')

@section('style')
<style>
  .ms-intro { font-size: 15px; line-height: 1.7; color: #475569; margin-bottom: 24px; }
  .ms-intro strong { color: #0f172a; }
  .ms-ident { border: 1px solid #e2e8f0; border-radius: 14px; padding: 16px 20px;
    background: #f8fafc; margin-bottom: 24px; }
  .ms-name { font-size: 17px; font-weight: 800; color: #0f172a; }
  .ms-meta { font-size: 12.5px; color: #64748b; margin-top: 3px; }
  .ms-pill {
    font-size: 11px; font-weight: 800; text-transform: uppercase; letter-spacing: .5px;
    padding: 5px 13px; border-radius: 999px; display: inline-block; margin-top: 10px;
    background: #e0f2fe; color: #075985;
  }
  .ms-content-box { border: 1px solid #e2e8f0; border-radius: 14px; overflow: hidden; margin-bottom: 22px; }
  .ms-head { background: #0f172a; color: #fff; padding: 12px 20px; font-size: 11.5px;
    font-weight: 800; letter-spacing: .6px; text-transform: uppercase; }
  .ms-body { padding: 16px 20px; font-size: 14px; color: #1e293b; line-height: 1.6; white-space: pre-wrap; }
  .ms-note { font-size: 12.5px; color: #94a3b8; line-height: 1.6; }
</style>
@endsection

@section('content')
  <p class="ms-intro">
    A new <strong>{{ $entry->category }}</strong> has been submitted on Mind Speak.
  </p>

  <div class="ms-ident">
    @if($entry->is_anonymous)
      <div class="ms-name">Anonymous</div>
      <div class="ms-meta">Submitted anonymously</div>
    @else
      <div class="ms-name">{{ optional($submitter)->getFullName() ?? '—' }}</div>
      <div class="ms-meta">
        {{ optional($submitter)->code ?: '—' }}
        &middot; {{ optional(optional($submitter)->department)->name ?: '—' }}
      </div>
    @endif
    <span class="ms-pill">{{ $entry->category }}</span>
  </div>

  <div class="ms-content-box">
    <div class="ms-head">Submission &middot; {{ $entry->created_at->format('d M Y, h:i A') }}</div>
    <div class="ms-body">{{ $entry->content }}</div>
  </div>

  @if($entry->attachment)
    <p class="ms-note">
      <strong>Attachment:</strong> This submission includes a file. Log in to HRX to download it.
    </p>
  @endif

  <p class="ms-note">
    You can review and act on this entry from the Mind Speak dashboard in HRX.
  </p>
@endsection
