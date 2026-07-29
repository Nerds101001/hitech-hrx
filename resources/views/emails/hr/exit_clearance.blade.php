@extends('emails.layout')

@section('title', ucfirst($exitType) . ' — ' . $leaver->getFullName())
@section('badge', $progress['done'] ? 'Clearance Complete' : 'Clearance Pending')
@section('header_title', $exitType === 'termination' ? 'Employee Termination' : 'Employee Resignation')
@section('greeting', 'Dear Team,')

@section('style')
<style>
  .ec-intro { font-size: 15px; line-height: 1.7; color: #475569; margin-bottom: 24px; }
  .ec-intro strong { color: #0f172a; }
  .ec-alert {
    background: #fef2f2; border-left: 4px solid #dc2626; border-radius: 10px;
    padding: 14px 18px; font-size: 13.5px; color: #991b1b; line-height: 1.6; margin-bottom: 24px;
  }
  .ec-ident { border: 1px solid #e2e8f0; border-radius: 14px; padding: 16px 20px;
    background: #f8fafc; margin-bottom: 24px; }
  .ec-name { font-size: 17px; font-weight: 800; color: #0f172a; }
  .ec-meta { font-size: 12.5px; color: #64748b; margin-top: 3px; }
  .ec-progress {
    font-size: 12px; font-weight: 800; text-transform: uppercase; letter-spacing: .5px;
    padding: 6px 14px; border-radius: 999px; display: inline-block; margin-top: 10px;
  }
  .ec-progress.done { background: #dcfce7; color: #166534; }
  .ec-progress.wait { background: #fef3c7; color: #92400e; }
  .ec-list { border: 1px solid #e2e8f0; border-radius: 14px; overflow: hidden; margin-bottom: 22px; }
  .ec-head { background: #0f172a; color: #fff; padding: 12px 20px; font-size: 11.5px;
    font-weight: 800; letter-spacing: .6px; text-transform: uppercase; }
  .ec-row { padding: 13px 20px; border-bottom: 1px solid #f1f5f9; font-size: 14px; }
  .ec-row:last-child { border-bottom: none; }
  .ec-dept { font-weight: 700; color: #0f172a; }
  .ec-state { float: right; font-size: 11.5px; font-weight: 800; padding: 2px 11px; border-radius: 999px; }
  .ec-state.cleared { background: #dcfce7; color: #166534; }
  .ec-state.pending { background: #fef3c7; color: #92400e; }
  .ec-state.blocked { background: #fee2e2; color: #991b1b; }
  .ec-remark { display: block; font-size: 12.5px; color: #64748b; margin-top: 5px; font-style: italic; }
  .ec-note { font-size: 12.5px; color: #94a3b8; line-height: 1.6; }
</style>
@endsection

@section('content')
  <p class="ec-intro">
    HR has recorded the {{ $exitType }} of <strong>{{ $leaver->getFullName() }}</strong>.
    Their system access has already been revoked. Please complete your department's
    clearance below so the exit can be formally closed.
  </p>

  <div class="ec-alert">
    <strong>Access revoked.</strong> This employee can no longer sign in. Please settle any
    dues, recover company assets and confirm there are no outstanding items from your side.
  </div>

  <div class="ec-ident">
    <div class="ec-name">{{ $leaver->getFullName() }}</div>
    <div class="ec-meta">
      {{ $leaver->code ?: '—' }}
      &middot; {{ optional($leaver->designation)->name ?: '—' }}
      &middot; {{ optional($leaver->department)->name ?: '—' }}
    </div>
    <span class="ec-progress {{ $progress['done'] ? 'done' : 'wait' }}">
      {{ $progress['done'] ? 'All clearances received' : $progress['label'] }}
    </span>
  </div>

  <div class="ec-list">
    <div class="ec-head">No-Objection Certificates</div>
    @foreach($clearances as $c)
      <div class="ec-row">
        <span class="ec-dept">{{ $c->label }}</span>
        <span class="ec-state {{ $c->status }}">{{ strtoupper($c->status) }}</span>
        @if($c->remarks)
          <span class="ec-remark">"{{ $c->remarks }}"
            @if($c->clearedBy) — {{ $c->clearedBy->getFullName() }} @endif
          </span>
        @endif
      </div>
    @endforeach
  </div>

  <p class="ec-note">
    @if($progress['done'])
      All departments have signed off. HR can now close this exit record.
    @else
      The {{ $exitType }} stays marked <strong>pending clearance</strong> until every
      department above has signed off. Remarks you add are visible to HR on the tracker.
    @endif
  </p>
@endsection
