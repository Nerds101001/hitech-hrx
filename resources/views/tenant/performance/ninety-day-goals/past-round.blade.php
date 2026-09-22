@php $configData = Helper::appClasses(); @endphp
@extends('layouts/contentNavbarLayout')

@section('title', '90-Day Goals — Round ' . $round->round_no)

@section('content')
@php
  $G = \App\Services\NinetyDayGoalService::class;
  $c = $d['cards'];
  $fmtL = fn ($v) => rtrim(rtrim(number_format((float) $v, 2), '0'), '.') ?: '0';
  $pctText = fn ($v) => $v === null ? '–' : rtrim(rtrim(number_format($v, 1), '0'), '.') . '%';
  $pctColor = fn ($v) => $v === null ? '#94a3b8' : ($v >= 66 ? '#007a7a' : ($v >= 33 ? '#f5a623' : '#ef4444'));
  $name = fn ($u) => trim(optional($u)->first_name . ' ' . optional($u)->last_name);

  /** Same look as the Goals grid, but read-only: no chip is clickable, nothing can be added or removed here. */
  $chip = function ($checkin) use ($G) {
      if (!$checkin || !$checkin->status) {
          return $checkin ? '<span class="ndg-chip ndg-missing">not sent</span>' : '<span class="ndg-none">·</span>';
      }
      [$bg, $fg] = $G::STATUS_COLORS[$checkin->status] ?? ['#64748b', '#ffffff'];
      $late = $checkin->is_late ? '<i class="ndg-late">late, sent ' . e(optional($checkin->submitted_at)->format('d M')) . '</i>' : '';

      return '<span class="ndg-chip" style="background:' . $bg . ';color:' . $fg . '">' . e($checkin->status) . '</span>' . $late;
  };
  $noteCell = function ($checkin) {
      if (!$checkin) {
          return '<span class="ndg-none">·</span>';
      }
      $h = $checkin->comment ? '<div class="ndg-n ndg-n-status">' . e($checkin->comment) . '</div>' : '';
      foreach ($checkin->notes as $n) {
          $who = trim(optional($n->author)->first_name . ' ' . optional($n->author)->last_name);
          $h .= '<div class="ndg-n"><b>' . e($who) . '</b><span>' . $n->created_at->format('d M') . '</span><div>' . e($n->body) . '</div></div>';
      }

      return $h ?: '<span class="ndg-none">·</span>';
  };
@endphp
<div class="layout-full-width animate__animated animate__fadeIn">

  <div class="d-flex flex-wrap align-items-center justify-content-between gap-3 mb-4 mx-4">
    <div>
      <div class="d-flex align-items-center gap-2 mb-1">
        <a href="{{ route('performance.ninety-day-goals.past') }}" class="text-muted" title="Back to Past rounds"><i class="bx bx-arrow-back"></i></a>
        <h3 class="fw-extrabold mb-0 text-dark">Round {{ $round->round_no }}@if($seesMany) · {{ $name($round->user) }}@endif</h3>
        <span class="ndg-closed-badge">closed</span>
      </div>
      <p class="text-muted mb-0 small">{{ $round->starts_on->format('d M Y') }} to {{ $round->ends_on->format('d M Y') }} · {{ optional($round->user->department)->name }}</p>
    </div>
  </div>

  @include('tenant.performance.ninety-day-goals._tabs')

  <div class="px-4">

  {{-- The headline: how much of the target landed, and the full spread of outcomes it took to get there. --}}
  <div class="ndg-hero mb-6">
    <div class="ndg-hero-num" style="color: {{ $pctColor($c['pct']) }}">{{ $pctText($c['pct']) }}</div>
    <div class="ndg-hero-what">of round target sold — {{ $fmtL($c['achieved']) }} ₹L of {{ $fmtL($c['target']) }} ₹L, across {{ $c['goals'] }} {{ \Illuminate\Support\Str::plural('customer', $c['goals']) }}</div>
    @php $statusTotal = max(1, array_sum($d['statuses'])); @endphp
    <div class="ndg-strip">
      @foreach($d['statuses'] as $st => $n)
        @continue($n === 0)
        @php [$bg] = $G::STATUS_COLORS[$st] ?? ['#94a3b8', '#fff']; @endphp
        <div class="ndg-seg" style="flex-grow:{{ $n }};background:{{ $bg }}" title="{{ $st }}: {{ $n }}">{{ $n }}</div>
      @endforeach
    </div>
    <div class="ndg-legend">
      @foreach($d['statuses'] as $st => $n)
        @continue($n === 0)
        @php [$bg] = $G::STATUS_COLORS[$st] ?? ['#94a3b8', '#fff']; @endphp
        <div class="ndg-lg"><span class="ndg-sw" style="background:{{ $bg }}"></span><div><b>{{ $n }}</b><span>{{ $st }} · {{ round($n / $statusTotal * 100) }}%</span></div></div>
      @endforeach
    </div>
  </div>

  <div class="row g-3 mb-6">
    @php
      $cards = [
        ['bx-target-lock', 'Customers', $c['goals']],
        ['bx-rupee', 'Monthly potential (₹L)', $fmtL($c['potential'])],
        ['bx-flag', 'Round target (₹L)', $fmtL($c['target'])],
        ['bx-trending-up', 'Sold (₹L)', $fmtL($c['achieved'])],
        ['bx-pie-chart-alt-2', 'Achievement', $pctText($c['pct'])],
        ['bx-cart-download', 'Converted', $c['converted']],
        ['bx-time-five', 'Reported on time', $c['discipline'] === null ? '–' : $c['discipline'] . '%'],
      ];
    @endphp
    @foreach($cards as [$icon, $label, $value])
      <div class="col-6 col-md-4 col-xl">
        <div class="ndg-kpi">
          <div class="ndg-kpi-l"><i class="bx {{ $icon }}"></i>{{ $label }}</div>
          <div class="ndg-kpi-v">{{ $value }}</div>
        </div>
      </div>
    @endforeach
  </div>

  {{-- The result, ranked by how much of the target actually landed — the number that matters, not the log of who said what. --}}
  <div class="hitech-card mb-6">
    <div class="card-body p-4 border-bottom">
      <div class="fw-bold text-dark">Result, by customer</div>
      <div class="small text-muted">Sold against target, best achievement first. Ended at {{ $round->ends_on->format('d M Y') }}.</div>
    </div>
    <div class="card-body p-0 table-responsive">
      <table class="table align-middle mb-0">
        <thead><tr>
          <th style="min-width:180px">Customer</th><th>Brand</th><th class="text-end">Monthly ₹L</th>
          <th class="text-end">Target ₹L</th><th class="text-end">Sold ₹L</th><th style="min-width:170px">Achievement</th><th>Final status</th>
        </tr></thead>
        <tbody>
        @foreach($d['rows']->sortByDesc(fn ($r) => [$r->pct ?? -1, $r->achieved])->values() as $r)
          @php [$bg, $fg] = $G::STATUS_COLORS[$r->status] ?? ['#e2e8f0', '#475569']; @endphp
          <tr>
            <td class="fw-semibold">{{ $r->goal->client_name }}</td>
            <td>{{ $r->brand }}</td>
            <td class="text-end">{{ $fmtL($r->potential) }}</td>
            <td class="text-end">{{ $fmtL($r->target) }}</td>
            <td class="text-end fw-semibold">{{ $fmtL($r->achieved) }}</td>
            <td>
              <div class="d-flex align-items-center gap-2">
                <div class="ndg-track flex-grow-1"><div class="ndg-fill" style="width:{{ min(100, (float) $r->pct) }}%;background:{{ $pctColor($r->pct) }}"></div></div>
                <b class="small" style="min-width:44px;text-align:right;color:{{ $pctColor($r->pct) }}">{{ $pctText($r->pct) }}</b>
              </div>
            </td>
            <td>@if($r->status)<span class="ndg-chip" style="background:{{ $bg }};color:{{ $fg }}">{{ $r->status }}</span>@else<span class="ndg-none">no status left</span>@endif</td>
          </tr>
        @endforeach
        </tbody>
      </table>
    </div>
  </div>

  {{-- Full week-by-week detail: exactly how the result above was reached, every status and every comment. --}}
  <div class="hitech-card mb-6">
    <div class="card-body p-4 border-bottom fw-bold text-dark">Week-by-week detail, W0 to W{{ $weeks - 1 }}</div>
    <div class="card-body p-0 ndg-scroll">
      <table class="table align-middle mb-0 ndg-grid">
        <thead>
          <tr>
            <th rowspan="2" style="min-width:180px">Customer</th><th rowspan="2" style="min-width:150px">Product</th>
            <th rowspan="2">Brand</th><th rowspan="2" style="min-width:130px">Goal</th><th rowspan="2" class="text-end">Monthly ₹ L</th>
            @for($w = 0; $w < $weeks; $w++)<th colspan="2" class="text-center ndg-wk ndg-wkhead">W{{ $w }}</th>@endfor
          </tr>
          <tr class="ndg-sub">
            @for($w = 0; $w < $weeks; $w++)<th class="ndg-st">Status</th><th>Comment</th>@endfor
          </tr>
        </thead>
        <tbody>
        @foreach($goals as $g)
          @php $cks = $g->checkins->keyBy('week_no'); @endphp
          <tr>
            <td class="fw-semibold">{{ $g->client_name }}</td>
            <td>{{ $g->product }}</td>
            <td>{{ $g->brand }}</td>
            <td>{{ $g->goal ?: $g->goal_type }}</td>
            <td class="text-end fw-semibold">{{ $fmtL($g->monthly_potential) }}</td>
            @for($w = 0; $w < $weeks; $w++)
              @php $ck = $cks->get($w); @endphp
              <td class="ndg-st">{!! $chip($ck) !!}</td>
              <td class="ndg-cm">{!! $noteCell($ck) !!}</td>
            @endfor
          </tr>
        @endforeach
        </tbody>
      </table>
    </div>
  </div>

  </div>
</div>

<style>
  .ndg-closed-badge { background: #f1f5f9; color: #64748b; font-size: .68rem; font-weight: 700; padding: 3px 10px; border-radius: 999px; }
  .ndg-hero { background: #fff; border: 1px solid #e2e8f0; border-radius: 14px; padding: 24px 26px; }
  .ndg-hero-num { font-size: clamp(40px, 6vw, 64px); font-weight: 800; line-height: 1; font-variant-numeric: tabular-nums; letter-spacing: -.02em; }
  .ndg-hero-what { color: #64748b; font-size: .92rem; margin-top: 6px; }
  .ndg-strip { display: flex; gap: 2px; height: 34px; border-radius: 6px; overflow: hidden; margin-top: 20px; background: #eef2f5; }
  .ndg-seg { display: flex; align-items: center; justify-content: center; color: #fff; font-weight: 700; font-size: .78rem; min-width: 2px; }
  .ndg-legend { display: flex; flex-wrap: wrap; gap: 16px 22px; margin-top: 16px; }
  .ndg-lg { display: flex; gap: 8px; align-items: flex-start; }
  .ndg-lg .ndg-sw { width: 11px; height: 11px; border-radius: 3px; margin-top: 4px; flex: none; }
  .ndg-lg b { display: block; font-size: 1.05rem; font-weight: 800; line-height: 1.2; }
  .ndg-lg span { display: block; font-size: .72rem; color: #64748b; }
  .ndg-kpi { border: 1px solid #e2e8f0; border-radius: 12px; padding: 14px 16px; background: #fff; height: 100%; }
  .ndg-kpi-l { font-size: .68rem; font-weight: 700; text-transform: uppercase; letter-spacing: .04em; color: #64748b; display: flex; align-items: center; gap: 5px; }
  .ndg-kpi-l i { color: #007a7a; font-size: 1rem; }
  .ndg-kpi-v { font-size: 1.4rem; font-weight: 800; color: #0f172a; margin-top: 4px; font-variant-numeric: tabular-nums; }
  .ndg-scroll { max-height: 70vh; overflow: auto; }
  .ndg-scroll thead th { position: sticky; top: 0; z-index: 2; background: #f3f6f9; }
  .ndg-grid th, .ndg-grid td { white-space: nowrap; }
  .ndg-wk { font-size: .78rem; }
  .ndg-wkhead { border-left: 2px solid #cfe0de; }
  .ndg-sub th { font-size: .62rem; text-transform: uppercase; letter-spacing: .04em; color: #64748b; font-weight: 700; }
  .ndg-grid td.ndg-st { border-left: 2px solid #cfe0de; min-width: 118px; text-align: center; }
  .ndg-grid td.ndg-cm { white-space: normal; min-width: 200px; max-width: 260px; font-size: .78rem; line-height: 1.35; color: #334155; }
  .ndg-chip { display: inline-block; padding: 3px 9px; border-radius: 6px; font-size: .68rem; font-weight: 700; }
  .ndg-chip.ndg-missing { background: #fff; color: #b42318; border: 1px dashed #f0a5a0; font-weight: 600; }
  .ndg-late { display: block; font-size: .6rem; color: #b42318; font-weight: 700; margin-top: 2px; }
  .ndg-none { color: #cbd5e1; }
  .ndg-n { padding: 4px 0 5px; border-bottom: 1px dashed #e2e8f0; }
  .ndg-n b { font-size: .72rem; color: #0f172a; }
  .ndg-n span { font-size: .66rem; color: #94a3b8; margin-left: 6px; }
  .ndg-n-status { color: #334155; }
  .ndg-cm { padding: 16px 24px; border-bottom: 1px solid #eef2f5; }
  .ndg-cm:last-child { border-bottom: 0; }
  .ndg-cm-head { display: flex; flex-wrap: wrap; align-items: center; gap: 10px; margin-bottom: 8px; }
  .ndg-cm-body { color: #1e293b; line-height: 1.55; white-space: pre-line; max-width: 900px; }
  .ndg-track { height: 7px; background: #eef2f5; border-radius: 99px; overflow: hidden; }
  .ndg-fill { height: 100%; border-radius: 99px; min-width: 2px; }
</style>
@endsection
