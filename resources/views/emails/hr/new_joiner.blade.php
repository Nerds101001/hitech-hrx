@extends('emails.layout')

@section('title', 'New Joiner — ' . $joiner->getFullName())
@section('badge', 'Onboarding Approved')
@section('header_title', 'Welcome Aboard, ' . \Illuminate\Support\Str::of($joiner->getFullName())->explode(' ')->first() . '!')
@section('greeting', 'Dear Team,')

@section('style')
<style>
  .nj-intro { font-size: 15px; line-height: 1.75; color: #475569; margin-bottom: 28px; }
  .nj-intro strong { color: #0f172a; }

  /* ---- hero ---- */
  .nj-hero {
    border: 1px solid #e2e8f0; border-radius: 18px; overflow: hidden;
    margin-bottom: 26px; background: #ffffff;
  }
  .nj-hero-top {
    background: linear-gradient(135deg, #003d3d 0%, #005a5a 55%, #0d9488 100%);
    padding: 26px 26px 22px; text-align: center; position: relative;
  }
  .nj-avatar {
    width: 78px; height: 78px; border-radius: 50%;
    border: 4px solid rgba(255,255,255,0.9); display: block; margin: 0 auto 12px;
    object-fit: cover; background: #ffffff;
  }
  .nj-avatar-fallback {
    width: 78px; height: 78px; border-radius: 50%; margin: 0 auto 12px;
    border: 4px solid rgba(255,255,255,0.9); background: rgba(255,255,255,0.16);
    color: #ffffff; font-size: 27px; font-weight: 800; line-height: 70px; text-align: center;
  }
  .nj-name { font-size: 21px; font-weight: 800; color: #ffffff; letter-spacing: -0.3px; }
  .nj-role { font-size: 13.5px; color: rgba(255,255,255,0.88); margin-top: 4px; font-weight: 600; }
  .nj-chip {
    display: inline-block; margin-top: 12px; padding: 5px 15px; border-radius: 999px;
    background: rgba(255,255,255,0.18); border: 1px solid rgba(255,255,255,0.28);
    color: #ffffff; font-size: 11px; font-weight: 800; letter-spacing: 0.7px; text-transform: uppercase;
  }

  /* ---- details ---- */
  table.nj-table { width: 100%; border-collapse: collapse; }
  table.nj-table td { padding: 13px 26px; font-size: 14px; border-bottom: 1px solid #f1f5f9; }
  table.nj-table tr:last-child td { border-bottom: none; }
  table.nj-table tr:nth-child(even) td { background: #fbfdfe; }
  td.nj-label { color: #64748b; font-weight: 600; width: 45%; }
  td.nj-value { color: #0f172a; font-weight: 700; }

  /* ---- actions ---- */
  .nj-actions-head {
    font-size: 12px; font-weight: 800; letter-spacing: 0.7px; text-transform: uppercase;
    color: #0f766e; margin: 0 0 12px 2px;
  }
  .nj-action {
    border: 1px solid #e2e8f0; border-left: 4px solid #0d9488; border-radius: 12px;
    padding: 14px 18px; margin-bottom: 10px; background: #ffffff;
  }
  .nj-action-who {
    font-size: 11px; font-weight: 800; text-transform: uppercase; letter-spacing: 0.6px;
    color: #0d9488; margin-bottom: 4px;
  }
  .nj-action-what { font-size: 13.5px; color: #334155; line-height: 1.6; }
  .nj-foot {
    margin-top: 26px; padding-top: 18px; border-top: 1px solid #f1f5f9;
    font-size: 12.5px; color: #94a3b8; line-height: 1.65;
  }
</style>
@endsection

@section('content')
  <p class="nj-intro">
    Please join us in welcoming <strong>{{ $joiner->getFullName() }}</strong> to the team.
    Their onboarding has been reviewed and approved by HR, and their employee record is now
    active in HRX. Full details are below for your records and for any action required at your end.
  </p>

  <div class="nj-hero">
    <div class="nj-hero-top">
      @if($joiner->getProfilePicture())
        <img src="{{ $joiner->getProfilePicture() }}" alt="" class="nj-avatar">
      @else
        <div class="nj-avatar-fallback">{{ $joiner->getInitials() }}</div>
      @endif
      <div class="nj-name">{{ $joiner->getFullName() }}</div>
      <div class="nj-role">
        {{ optional($joiner->designation)->name ?: 'Team Member' }}
        @if(optional($joiner->department)->name) &middot; {{ $joiner->department->name }} @endif
      </div>
      @if($joiner->code)
        <div class="nj-chip">Employee ID {{ $joiner->code }}</div>
      @endif
    </div>

    <table class="nj-table">
      @foreach($details as $label => $value)
        @continue($label === 'Employee Name')
        <tr>
          <td class="nj-label">{{ $label }}</td>
          <td class="nj-value">{{ $value }}</td>
        </tr>
      @endforeach
    </table>
  </div>

  <p class="nj-actions-head">Next steps</p>

  <div class="nj-action">
    <div class="nj-action-who">Accounts</div>
    <div class="nj-action-what">
      Complete the attendance setup — record the employee ID and biometric enrolment — so
      payroll and attendance begin correctly from the date of joining.
      Identity and banking documents have been sent separately.
    </div>
  </div>

  <div class="nj-action">
    <div class="nj-action-who">Reporting Manager</div>
    <div class="nj-action-what">
      Arrange workspace and system access, share the induction plan for the first week,
      and confirm reporting lines with the team.
    </div>
  </div>

  <div class="nj-action">
    <div class="nj-action-who">Human Resources</div>
    <div class="nj-action-what">
      Probation review is scheduled from the date of joining. Policy acknowledgements and
      training assignments are available in the employee's HRX profile.
    </div>
  </div>

  <p class="nj-foot">
    This is an automated notification from HRX, sent when an onboarding is approved.
    The full employee profile is available in the portal under Employees.
  </p>
@endsection
