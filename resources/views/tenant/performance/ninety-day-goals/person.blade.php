@php $configData = Helper::appClasses(); @endphp
@extends('layouts/contentNavbarLayout')

@php $name = fn ($u) => trim(optional($u)->first_name . ' ' . optional($u)->last_name); @endphp
@section('title', $name($target) . ' — 90-Day Goals')

@section('content')
@php
  $G = \App\Services\NinetyDayGoalService::class;
  $c = $career['cards'];
  $fmtL = fn ($v) => rtrim(rtrim(number_format((float) $v, 2), '0'), '.') ?: '0';
  $pctText = fn ($v) => $v === null ? '–' : rtrim(rtrim(number_format($v, 1), '0'), '.') . '%';
  $pctColor = fn ($v) => $v === null ? '#94a3b8' : ($v >= 66 ? '#007a7a' : ($v >= 33 ? '#f5a623' : '#ef4444'));
@endphp
<div class="layout-full-width animate__animated animate__fadeIn">

  <div class="d-flex flex-wrap align-items-center justify-content-between gap-3 mb-4 mx-4">
    <div>
      <a href="{{ route('performance.ninety-day-goals.program-dashboard') }}" class="text-muted small d-inline-flex align-items-center gap-1 mb-2"><i class="bx bx-arrow-back"></i> Back to Dashboard</a>
      <h3 class="fw-extrabold mb-1 text-dark">{{ $name($target) }}</h3>
      <p class="text-muted mb-0 small">{{ optional($target->department)->name }} · {{ $rounds->count() }} {{ \Illuminate\Support\Str::plural('round', $rounds->count()) }} in total @if($active)· Round {{ $active->round_no }} running now @endif</p>
    </div>
  </div>

  @include('tenant.performance.ninety-day-goals._tabs')

  <div class="px-4">

  {{-- Their whole record: every round, active and closed, added together. --}}
  <div class="ndg-dial mb-6">
    <div class="ndg-dial-l">
      <div class="ndg-dial-t">Career achievement</div>
      <div class="ndg-dial-v" style="color: {{ $pctColor($c['pct']) }}">{{ $pctText($c['pct']) }}</div>
      <div class="ndg-dial-s">{{ $fmtL($c['achieved']) }} ₹L sold of {{ $fmtL($c['target']) }} ₹L targeted, across {{ $c['goals'] }} customer {{ \Illuminate\Support\Str::plural('slot', $c['goals']) }}</div>
    </div>
    <div class="ndg-dial-r">
      <div class="ndg-dr"><b>{{ $c['converted'] }}</b><span>Converted to an order</span></div>
      <div class="ndg-dr"><b>{{ $c['discipline'] === null ? '–' : $c['discipline'] . '%' }}</b><span>Reported on time</span></div>
      <div class="ndg-dr"><b>{{ $rounds->where('status', 'closed')->count() }}</b><span>Rounds finished</span></div>
    </div>
  </div>

  <div class="row g-4 mb-6">
    <div class="col-lg-6">
      <div class="hitech-card h-100">
        <div class="card-body p-4 border-bottom fw-bold text-dark">Top customers by target</div>
        <div class="card-body">
          @php $maxP = max(1, (float) optional($career['topCustomers']->first())->potential); @endphp
          @forelse($career['topCustomers'] as $r)
            <div class="ndg-bar">
              <div class="ndg-bar-top"><span class="ndg-bar-name">{{ $r->goal->client_name }}<em>{{ $r->brand }}</em></span><span class="ndg-bar-val">{{ $fmtL($r->potential) }} L</span></div>
              <div class="ndg-track"><div class="ndg-fill" style="width:{{ round($r->potential / $maxP * 100) }}%"></div></div>
            </div>
          @empty
            <div class="text-muted small">No customers yet.</div>
          @endforelse
        </div>
      </div>
    </div>
    <div class="col-lg-6">
      <div class="hitech-card h-100">
        <div class="card-body p-4 border-bottom fw-bold text-dark">Every round</div>
        <div class="card-body p-0 table-responsive">
          <table class="table align-middle mb-0">
            <thead><tr><th>Round</th><th>Dates</th><th class="text-end">Customers</th><th style="min-width:140px">Achievement</th></tr></thead>
            <tbody>
            @forelse($rounds as $r)
              <tr class="{{ $r->status === 'active' ? '' : 'ndg-click' }}" @if($r->status !== 'active') onclick="location.href='{{ route('performance.ninety-day-goals.past.show', $r->id) }}'" @endif>
                <td class="fw-semibold">R{{ $r->round_no }} @if($r->status === 'active')<span class="ndg-live">running</span>@endif</td>
                <td class="small text-muted">{{ $r->starts_on->format('d M') }}–{{ $r->ends_on->format('d M Y') }}</td>
                <td class="text-end">{{ $r->stats['goals'] }}</td>
                <td>
                  @if($r->status === 'active')
                    <span class="text-muted small">in progress</span>
                  @else
                    <div class="d-flex align-items-center gap-2">
                      <div class="ndg-track flex-grow-1"><div class="ndg-fill" style="width:{{ min(100, (float) $r->stats['pct']) }}%;background:{{ $pctColor($r->stats['pct']) }}"></div></div>
                      <b class="small" style="min-width:40px;text-align:right;color:{{ $pctColor($r->stats['pct']) }}">{{ $pctText($r->stats['pct']) }}</b>
                    </div>
                  @endif
                </td>
              </tr>
            @empty
              <tr><td colspan="4" class="text-center text-muted p-4">No rounds yet.</td></tr>
            @endforelse
            </tbody>
          </table>
        </div>
      </div>
    </div>
  </div>

  </div>
</div>

<style>
  .ndg-dial { background: #fff; border: 1px solid #e2e8f0; border-radius: 14px; padding: 24px 26px; display: flex; flex-wrap: wrap; gap: 24px; justify-content: space-between; align-items: center; }
  .ndg-dial-t { font-size: .72rem; font-weight: 700; text-transform: uppercase; letter-spacing: .04em; color: #64748b; }
  .ndg-dial-v { font-size: clamp(40px, 5.5vw, 58px); font-weight: 800; line-height: 1; font-variant-numeric: tabular-nums; margin-top: 4px; }
  .ndg-dial-s { color: #64748b; font-size: .85rem; margin-top: 6px; max-width: 46ch; }
  .ndg-dial-r { display: flex; gap: 28px; }
  .ndg-dr b { display: block; font-size: 1.5rem; font-weight: 800; color: #0f172a; font-variant-numeric: tabular-nums; }
  .ndg-dr span { font-size: .72rem; color: #64748b; }
  .ndg-bar { margin-bottom: 14px; }
  .ndg-bar:last-child { margin-bottom: 0; }
  .ndg-bar-top { display: flex; align-items: center; gap: 10px; margin-bottom: 5px; }
  .ndg-bar-name { flex: 1; min-width: 0; font-weight: 600; font-size: .86rem; color: #0f172a; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
  .ndg-bar-name em { display: block; font-style: normal; font-weight: 400; font-size: .7rem; color: #94a3b8; }
  .ndg-bar-val { font-weight: 800; font-size: .86rem; white-space: nowrap; }
  .ndg-track { height: 7px; background: #eef2f5; border-radius: 99px; overflow: hidden; }
  .ndg-fill { height: 100%; background: #007a7a; border-radius: 99px; min-width: 2px; }
  .ndg-live { display: inline-block; margin-left: 6px; font-size: .62rem; font-weight: 700; color: #007a7a; background: #f0f7f6; padding: 1px 7px; border-radius: 999px; vertical-align: middle; }
  .ndg-click { cursor: pointer; }
  .ndg-click:hover td { background: #f8fafc; }
</style>
@endsection
