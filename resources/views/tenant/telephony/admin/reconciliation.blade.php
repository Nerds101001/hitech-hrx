@extends('layouts/layoutMaster')

@section('title', 'Telephony reconciliation')

@section('content')
@include('tenant.telephony._partials._helpers')
<div class="layout-full-width">

  @include('tenant.telephony._partials._header', [
    'title' => 'Reconciliation',
    'subtitle' => 'The nightly proof that HRX and the CentroVision CDR agree. A gap is a defect to root-cause, never a tolerance to widen.',
    'crumb' => 'Reconciliation',
  ])

  <div class="px-4">

    <div class="row g-3 mb-4">
      {{-- The acceptance criterion is seven CONSECUTIVE zero-gap days, so this
           counts the current streak. A total would let one old pass flatter a
           run of failures. --}}
      <div class="col-12 col-md-4">
        <div class="hitech-card-white border-0 shadow-sm rounded-4 p-3 h-100"
             style="border-left:4px solid {{ $streak >= 7 ? '#15803d' : '#b45309' }} !important;">
          <div class="d-flex align-items-center gap-2 mb-1">
            <i class="bx bx-check-double" style="font-size:1.1rem; color:{{ $streak >= 7 ? '#15803d' : '#b45309' }};"></i>
            <span class="text-muted small">Consecutive clean days</span>
          </div>
          <div style="font-size:1.8rem; font-weight:800; color:{{ $streak >= 7 ? '#15803d' : '#b45309' }};">
            {{ $streak }} <span class="text-muted" style="font-size:1rem; font-weight:600;">/ 7</span>
          </div>
          <p class="text-muted small mb-0 mt-1">
            {{ $streak >= 7
                ? 'The reconciliation acceptance criterion is met.'
                : 'Seven consecutive zero-gap days are required before sign-off.' }}
          </p>
        </div>
      </div>

      <div class="col-12 col-md-4">
        <div class="hitech-card-white border-0 shadow-sm rounded-4 p-3 h-100">
          <div class="d-flex align-items-center gap-2 mb-1">
            <i class="bx bx-calendar-check" style="font-size:1.1rem; color:#0f172a;"></i>
            <span class="text-muted small">Last run</span>
          </div>
          @if($lastRun)
            <div style="font-size:1.3rem; font-weight:800;">{{ $lastRun->period_date }}</div>
            <p class="text-muted small mb-0 mt-1">
              Vendor {{ number_format($lastRun->vendor_count) }} ·
              HRX {{ number_format($lastRun->local_count) }} ·
              gap {{ $lastRun->count_gap }}
            </p>
          @else
            <div style="font-size:1.3rem; font-weight:800; color:#b45309;">Never</div>
            <p class="text-muted small mb-0 mt-1">
              The scheduled run fires at 02:00. Run one by hand to test the gateway connection.
            </p>
          @endif
        </div>
      </div>

      <div class="col-12 col-md-4">
        <div class="hitech-card-white border-0 shadow-sm rounded-4 p-3 h-100">
          <div class="d-flex align-items-center gap-2 mb-2">
            <i class="bx bx-play-circle" style="font-size:1.1rem; color:var(--hitech-primary, #008080);"></i>
            <span class="text-muted small">Run one now</span>
          </div>
          <form method="POST" action="{{ route('user.telephony.admin.reconciliation.run') }}" class="d-flex gap-2">
            @csrf
            <input type="date" name="date" class="form-control form-control-sm"
                   value="{{ now()->subDay()->toDateString() }}"
                   max="{{ now()->toDateString() }}" required>
            <button class="btn btn-hitech-primary btn-sm text-nowrap" type="submit">Run</button>
          </form>
          <p class="text-muted mb-0 mt-2" style="font-size:.72rem;">
            Pulls the CDR for that day and backfills anything the webhook never delivered.
            Safe to re-run — replays collapse to a single event.
          </p>
        </div>
      </div>
    </div>

    <div class="hitech-card-white border-0 shadow-sm rounded-4 overflow-hidden">
      <div class="table-responsive">
        <table class="table mb-0">
          <thead>
            <tr>
              <th>Date</th>
              <th class="text-end">CentroVision</th>
              <th class="text-end">HRX</th>
              <th class="text-end">Count gap</th>
              <th class="text-end">Talk gap</th>
              <th class="text-end">Backfilled</th>
              <th>Result</th>
              <th>Missing call ids</th>
            </tr>
          </thead>
          <tbody>
            @forelse($runs as $r)
              @php
                $tone = match ($r->result) {
                  'pass'        => ['#dcfce7', '#15803d'],
                  'investigate' => ['#fef3c7', '#92400e'],
                  'failed'      => ['#fee2e2', '#991b1b'],
                  default       => ['#f1f5f9', '#475569'],
                };
              @endphp
              <tr>
                <td class="small fw-semibold text-nowrap">{{ $r->period_date }}</td>
                <td class="text-end small">{{ number_format($r->vendor_count) }}</td>
                <td class="text-end small">{{ number_format($r->local_count) }}</td>
                <td class="text-end small fw-semibold {{ $r->count_gap != 0 ? 'text-danger' : 'text-success' }}">
                  {{ $r->count_gap > 0 ? '+' : '' }}{{ $r->count_gap }}
                </td>
                <td class="text-end small {{ abs($r->talk_gap_seconds) > 0 ? 'text-warning' : 'text-muted' }}">
                  {{ $r->talk_gap_seconds > 0 ? '+' : '' }}{{ $r->talk_gap_seconds }}s
                </td>
                <td class="text-end small">{{ number_format($r->backfilled) }}</td>
                <td>
                  <span class="badge rounded-pill" style="background:{{ $tone[0] }}; color:{{ $tone[1] }};">
                    {{ ucfirst($r->result) }}
                  </span>
                  @if($r->error)
                    <div class="text-danger" style="font-size:.7rem;">{{ \Illuminate\Support\Str::limit($r->error, 70) }}</div>
                  @endif
                </td>
                <td class="small text-muted" style="max-width:280px;">
                  @if(!empty($r->missing_call_uids))
                    {{ \Illuminate\Support\Str::limit(implode(', ', array_slice($r->missing_call_uids, 0, 4)), 70) }}
                    @if(count($r->missing_call_uids) > 4)
                      <span class="text-nowrap">+{{ count($r->missing_call_uids) - 4 }} more</span>
                    @endif
                  @else
                    —
                  @endif
                </td>
              </tr>
            @empty
              <tr>
                <td colspan="8" class="text-center py-5">
                  <i class="bx bx-check-double text-muted" style="font-size:2.4rem;"></i>
                  <p class="text-muted mb-1 mt-2">No reconciliation runs yet.</p>
                  <p class="text-muted small mb-0">
                    This needs CentroVision's <code>GET /cdr</code> endpoint, which they have not
                    built yet — see centrovision-required-v1.yaml, priority 1.
                  </p>
                </td>
              </tr>
            @endforelse
          </tbody>
        </table>
      </div>

      @if($runs->hasPages())
        <div class="p-3 border-top">{{ $runs->links() }}</div>
      @endif
    </div>

  </div>
</div>
@endsection
