@extends('emails.layout')

@php
  $word = $kind === 'warning' ? 'Warning' : 'Complaint';
  $typeLabel = $kind === 'warning' ? ($notice->warning_type ?? '—') : ($notice->complaint_type ?? '—');
  $date = $kind === 'warning' ? $notice->warning_date : $notice->complaint_date;
@endphp

@section('title', $word . ' — ' . $employee->getFullName())
@section('badge', $word . ' Recorded')
@section('header_title', 'Employee ' . $word)
@section('greeting', 'Dear Team,')

@section('style')
<style>
  .ln-intro { font-size: 15px; line-height: 1.7; color: #475569; margin-bottom: 24px; }
  .ln-intro strong { color: #0f172a; }
  .ln-ident { border: 1px solid #e2e8f0; border-radius: 14px; padding: 16px 20px;
    background: #f8fafc; margin-bottom: 24px; }
  .ln-name { font-size: 17px; font-weight: 800; color: #0f172a; }
  .ln-meta { font-size: 12.5px; color: #64748b; margin-top: 3px; }
  .ln-pill {
    font-size: 11px; font-weight: 800; text-transform: uppercase; letter-spacing: .5px;
    padding: 5px 13px; border-radius: 999px; display: inline-block; margin-top: 10px;
  }
  .ln-pill.low { background: #e0f2fe; color: #075985; }
  .ln-pill.medium { background: #fef3c7; color: #92400e; }
  .ln-pill.high, .ln-pill.critical { background: #fee2e2; color: #991b1b; }
  .ln-desc { border: 1px solid #e2e8f0; border-radius: 14px; overflow: hidden; margin-bottom: 22px; }
  .ln-head { background: #0f172a; color: #fff; padding: 12px 20px; font-size: 11.5px;
    font-weight: 800; letter-spacing: .6px; text-transform: uppercase; }
  .ln-body { padding: 16px 20px; font-size: 14px; color: #1e293b; line-height: 1.6; }
  .ln-note { font-size: 12.5px; color: #94a3b8; line-height: 1.6; }
</style>
@endsection

@section('content')
  <p class="ln-intro">
    HR has recorded a {{ strtolower($word) }} for <strong>{{ $employee->getFullName() }}</strong>,
    filed by <strong>{{ $issuedBy ? $issuedBy->getFullName() : '—' }}</strong>.
  </p>

  <div class="ln-ident">
    <div class="ln-name">{{ $employee->getFullName() }}</div>
    <div class="ln-meta">
      {{ $employee->code ?: '—' }}
      &middot; {{ optional($employee->designation)->name ?: '—' }}
      &middot; {{ optional($employee->department)->name ?: '—' }}
    </div>
    <span class="ln-pill {{ strtolower($notice->severity ?? '') }}">
      {{ ucfirst($notice->severity ?? '—') }} severity
    </span>
  </div>

  <div class="ln-desc">
    <div class="ln-head">{{ ucfirst(str_replace('_', ' ', $typeLabel)) }} &middot; {{ $date ? \Carbon\Carbon::parse($date)->format('d M Y') : '—' }}</div>
    <div class="ln-body">{{ $notice->description }}</div>
  </div>

  <p class="ln-note">
    This is an automated notice. The employee can see this record, and any attachment, on
    their own "My Warnings &amp; Complaints" page in HRX.
  </p>
@endsection
