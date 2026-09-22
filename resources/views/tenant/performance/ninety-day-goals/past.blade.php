@php $configData = Helper::appClasses(); @endphp
@extends('layouts/contentNavbarLayout')

@section('title', '90-Day Goals Past Rounds')

@section('content')
@php
  $fmtL = fn ($v) => rtrim(rtrim(number_format((float) $v, 2), '0'), '.') ?: '0';
  $pctText = fn ($v) => $v === null ? '–' : rtrim(rtrim(number_format($v, 1), '0'), '.') . '%';
  $pctColor = fn ($v) => $v === null ? '#94a3b8' : ($v >= 66 ? '#007a7a' : ($v >= 33 ? '#f5a623' : '#ef4444'));
  $name = fn ($u) => trim(optional($u)->first_name . ' ' . optional($u)->last_name);
@endphp
<div class="layout-full-width animate__animated animate__fadeIn">

  <div class="d-flex flex-wrap align-items-center justify-content-between gap-3 mb-4 mx-4">
    <div>
      <h3 class="fw-extrabold mb-1 text-dark">90-Day Goals</h3>
      <p class="text-muted mb-0 small">Who actually delivered across every finished round, and each round kept on its own to open and check.</p>
    </div>
  </div>

  @include('tenant.performance.ninety-day-goals._tabs')

  <div class="px-4">

  @if($seesMany && $leaderboard && $leaderboard->isNotEmpty())
    <div class="hitech-card mb-6">
      <div class="card-body p-4 border-bottom">
        <div class="fw-bold text-dark">Closed-round record, by salesperson</div>
        <div class="small text-muted">Every finished round added together — what they were targeting against what they actually sold.</div>
      </div>
      <div class="card-body p-0 table-responsive">
        <table class="table align-middle mb-0">
          <thead><tr>
            <th>#</th><th>Salesperson</th><th class="text-end">Rounds</th><th class="text-end">Customers</th>
            <th class="text-end">Target ₹L</th><th class="text-end">Sold ₹L</th><th style="min-width:170px">Achievement</th>
            <th class="text-end">Converted</th><th class="text-end">Reported on time</th>
          </tr></thead>
          <tbody>
          @foreach($leaderboard as $i => $u)
            <tr>
              <td class="text-muted">{{ $i + 1 }}</td>
              <td><span class="fw-semibold text-dark">{{ $u['name'] }}</span><div class="small text-muted">{{ $u['department'] }}</div></td>
              <td class="text-end">{{ $rounds->where('user_id', $u['id'])->count() }}</td>
              <td class="text-end">{{ $u['goals'] }}</td>
              <td class="text-end">{{ $fmtL($u['target']) }}</td>
              <td class="text-end">{{ $fmtL($u['achieved']) }}</td>
              <td>
                <div class="d-flex align-items-center gap-2">
                  <div class="ndg-track flex-grow-1"><div class="ndg-fill" style="width:{{ min(100, (float) $u['pct']) }}%;background:{{ $pctColor($u['pct']) }}"></div></div>
                  <b class="small" style="min-width:44px;text-align:right;color:{{ $pctColor($u['pct']) }}">{{ $pctText($u['pct']) }}</b>
                </div>
              </td>
              <td class="text-end">{{ $u['converted'] }}</td>
              <td class="text-end">{{ $u['discipline'] === null ? '–' : $u['discipline'] . '%' }}</td>
            </tr>
          @endforeach
          </tbody>
        </table>
      </div>
    </div>
  @endif

  @if($rounds->isEmpty())
    <div class="hitech-card"><div class="card-body p-5 text-center text-muted">
      No round has finished yet. A round closes itself 90 days after it starts, and shows up here from then on.
    </div></div>
  @else
    @if($seesMany && $leaderboard && $leaderboard->isNotEmpty())<div class="fw-bold text-dark mb-3">Every round, to open</div>@endif
    @foreach($rounds->groupBy('user_id') as $userRounds)
      @php $u = $userRounds->first()->user; @endphp
      <div class="mb-6">
        @if($seesMany)
          <div class="fw-bold text-dark mb-3">{{ $name($u) }} <span class="text-muted fw-normal small">· {{ optional($u->department)->name }}</span></div>
        @endif
        <div class="row g-3">
          @foreach($userRounds as $r)
            @php $s = $r->stats; @endphp
            <div class="col-md-6 col-xl-4">
              <a href="{{ route('performance.ninety-day-goals.past.show', $r->id) }}" class="ndg-round-card">
                <div class="d-flex align-items-center justify-content-between mb-2">
                  <span class="fw-bold text-dark">Round {{ $r->round_no }}</span>
                  <span class="ndg-round-pct" style="color: {{ $pctColor($s['pct']) }}">{{ $pctText($s['pct']) }}</span>
                </div>
                <div class="small text-muted mb-3">{{ $r->starts_on->format('d M') }} to {{ $r->ends_on->format('d M Y') }}</div>
                <div class="d-flex flex-wrap gap-3 small">
                  <span><b class="text-dark">{{ $s['goals'] }}</b> customers</span>
                  <span><b class="text-dark">{{ $fmtL($s['potential']) }}</b> ₹L potential</span>
                  <span><b class="text-dark">{{ $fmtL($s['achieved']) }}</b> ₹L sold</span>
                  <span><b class="text-dark">{{ $s['converted'] }}</b> converted</span>
                </div>
                <div class="ndg-track mt-3"><div class="ndg-fill" style="width:{{ min(100, (float) $s['pct']) }}%;background:{{ $pctColor($s['pct']) }}"></div></div>
              </a>
            </div>
          @endforeach
        </div>
      </div>
    @endforeach
  @endif

  </div>
</div>

<style>
  .ndg-round-card { display: block; border: 1px solid #e2e8f0; border-radius: 12px; padding: 16px 18px; background: #fff; text-decoration: none; height: 100%; transition: border-color .15s; }
  .ndg-round-card:hover { border-color: #007a7a; }
  .ndg-round-pct { font-weight: 800; font-size: 1.05rem; }
  .ndg-track { height: 6px; background: #eef2f5; border-radius: 99px; overflow: hidden; }
  .ndg-fill { height: 100%; border-radius: 99px; min-width: 2px; }
</style>
@endsection
