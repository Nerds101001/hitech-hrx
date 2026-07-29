@extends('emails.layout')

@section('title', 'Documents — ' . $joiner->getFullName())
@section('badge', 'Confidential')
@section('header_title', 'New Joiner Documents')
@section('greeting', 'Dear Accounts Team,')

@section('style')
<style>
  .nd-intro { font-size: 15px; line-height: 1.7; color: #475569; margin-bottom: 24px; }
  .nd-ident {
    border: 1px solid #e2e8f0; border-radius: 14px; padding: 18px 22px;
    background: #f8fafc; margin-bottom: 24px;
  }
  .nd-ident-name { font-size: 17px; font-weight: 800; color: #0f172a; }
  .nd-ident-meta { font-size: 13px; color: #64748b; margin-top: 4px; }
  .nd-list { border: 1px solid #e2e8f0; border-radius: 14px; overflow: hidden; margin-bottom: 24px; }
  .nd-list-head {
    background: #0f172a; color: #ffffff; padding: 13px 20px;
    font-size: 12px; font-weight: 800; letter-spacing: 0.6px; text-transform: uppercase;
  }
  .nd-item {
    padding: 12px 20px; font-size: 14px; color: #0f172a; font-weight: 600;
    border-bottom: 1px solid #f1f5f9;
  }
  .nd-item:last-child { border-bottom: none; }
  .nd-empty { padding: 18px 20px; font-size: 14px; color: #b45309; background: #fffbeb; }
  .nd-warn {
    background: #fef2f2; border-left: 4px solid #dc2626; border-radius: 10px;
    padding: 15px 18px; font-size: 13px; color: #991b1b; line-height: 1.65;
  }
</style>
@endsection

@section('content')
  <p class="nd-intro">
    Please find attached the identity and banking documents submitted by the
    following employee during onboarding, for payroll and statutory records.
  </p>

  <div class="nd-ident">
    <div class="nd-ident-name">{{ $joiner->getFullName() }}</div>
    <div class="nd-ident-meta">
      {{ $details['Employee Code'] ?? '—' }}
      &middot; {{ $details['Designation'] ?? '—' }}
      &middot; {{ $details['Department'] ?? '—' }}
      &middot; Joined {{ $details['Date of Joining'] ?? '—' }}
    </div>
  </div>

  <div class="nd-list">
    <div class="nd-list-head">{{ count($documents) }} document{{ count($documents) === 1 ? '' : 's' }} attached</div>
    @forelse($documents as $doc)
      <div class="nd-item">{{ $doc['name'] }}</div>
    @empty
      <div class="nd-empty">
        No documents were found against this employee's onboarding record.
        Please follow up with HR before processing payroll.
      </div>
    @endforelse
  </div>

  <div class="nd-warn">
    <strong>Confidential.</strong> These documents contain personal and financial
    information. Please store them per company policy and do not forward this email.
  </div>
@endsection
