@extends('emails.layout')

@section('title', 'New Joiner — ' . $joiner->getFullName())
@section('badge', 'Onboarding Approved')
@section('header_title', 'A New Team Member Has Joined')
@section('greeting', 'Dear Team,')

@section('style')
<style>
  .nj-intro { font-size: 15px; line-height: 1.7; color: #475569; margin-bottom: 26px; }
  .nj-card {
    border: 1px solid #e2e8f0; border-radius: 16px; overflow: hidden;
    margin-bottom: 26px; background: #ffffff;
  }
  .nj-card-head {
    background: linear-gradient(135deg, #003d3d 0%, #005a5a 100%);
    padding: 20px 24px; color: #ffffff;
  }
  .nj-name { font-size: 19px; font-weight: 800; letter-spacing: -0.2px; }
  .nj-role { font-size: 13px; opacity: 0.85; margin-top: 3px; }
  table.nj-table { width: 100%; border-collapse: collapse; }
  table.nj-table td { padding: 13px 24px; font-size: 14px; border-bottom: 1px solid #f1f5f9; }
  table.nj-table tr:last-child td { border-bottom: none; }
  td.nj-label { color: #64748b; font-weight: 600; width: 44%; }
  td.nj-value { color: #0f172a; font-weight: 700; }
  .nj-note {
    background: #f0fdfa; border-left: 4px solid #0d9488; border-radius: 10px;
    padding: 15px 18px; font-size: 13.5px; color: #115e59; line-height: 1.65;
  }
  .nj-note strong { color: #0f766e; }
</style>
@endsection

@section('content')
  <p class="nj-intro">
    The onboarding formalities for <strong>{{ $joiner->getFullName() }}</strong> have been
    reviewed and approved by HR. Their employee record is now active in HRX.
    Full details are below for your records and any action required at your end.
  </p>

  <div class="nj-card">
    <div class="nj-card-head">
      <div class="nj-name">{{ $joiner->getFullName() }}</div>
      <div class="nj-role">
        {{ optional($joiner->designation)->name ?: 'Team Member' }}
        @if(optional($joiner->department)->name) &middot; {{ $joiner->department->name }} @endif
      </div>
    </div>

    <table class="nj-table">
      @foreach($details as $label => $value)
        <tr>
          <td class="nj-label">{{ $label }}</td>
          <td class="nj-value">{{ $value }}</td>
        </tr>
      @endforeach
    </table>
  </div>

  <div class="nj-note">
    <strong>Action required:</strong> Accounts to complete the attendance setup —
    employee ID and biometric enrolment — so payroll and attendance begin correctly
    from the date of joining. The reporting manager is requested to arrange
    workspace access and the induction plan.
  </div>
@endsection
