@php $configData = Helper::appClasses(); @endphp
@extends('layouts/contentNavbarLayout')

@section('title', '90-Day Goals Dashboard')

@section('content')
@php
  $G = \App\Services\NinetyDayGoalService::class;
  $c = $d['cards'];
  $fmtL = fn ($v) => rtrim(rtrim(number_format((float) $v, 2), '0'), '.') ?: '0';
  $pctText = fn ($v) => $v === null ? '–' : rtrim(rtrim(number_format($v, 1), '0'), '.') . '%';
  // Achievement colour: red under a third of target, amber under two thirds, teal beyond.
  $pctColor = fn ($v) => $v === null ? '#cbd5e1' : ($v >= 66 ? '#007a7a' : ($v >= 33 ? '#f5a623' : '#ef4444'));
  $name = fn ($u) => trim(optional($u)->first_name . ' ' . optional($u)->last_name);
  $maxCust = max(1, (float) optional($d['topCustomers']->first())->potential);
  $maxBrand = max(1, (float) optional($d['topBrands']->first())->potential);
  $maxAch = max(1, (float) optional($d['topAchieved']->first())->achieved);
  $maxStatus = max(1, max($d['statuses']));
@endphp
<div class="layout-full-width animate__animated animate__fadeIn">

  <div class="d-flex flex-wrap align-items-center justify-content-between gap-3 mb-4 mx-4">
    <div>
      <h3 class="fw-extrabold mb-1 text-dark">90-Day Goals</h3>
      <p class="text-muted mb-0 small">Where the target customers are, how much they are worth and how much of it has been sold. Values are in ₹ lakh.</p>
    </div>
  </div>

  @include('tenant.performance.ninety-day-goals._tabs')

  <div class="px-4">

  {{-- Filters --}}
  <div class="hitech-card mb-6">
    <div class="card-body p-4">
      <form method="GET" action="{{ route('performance.ninety-day-goals.dashboard') }}" class="row g-3 align-items-end">
        <div class="col-md-3">
          <label class="form-label fw-bold small text-muted text-uppercase mb-2">Round</label>
          <select name="round" class="form-select form-select-hitech" onchange="this.form.submit()">
            <option value="current" {{ $filters['round'] === 'current' ? 'selected' : '' }}>Current rounds</option>
            <option value="all" {{ $filters['round'] === 'all' ? 'selected' : '' }}>All rounds, including closed</option>
          </select>
        </div>
        @if($seesMany)
        <div class="col-md-4">
          <label class="form-label fw-bold small text-muted text-uppercase mb-2">Salesperson</label>
          <select name="user_id" class="form-select form-select-hitech" onchange="this.form.submit()">
            <option value="">Everyone</option>
            @foreach($people as $p)<option value="{{ $p->id }}" {{ (int) $filters['user_id'] === $p->id ? 'selected' : '' }}>{{ $p->first_name }} {{ $p->last_name }}</option>@endforeach
          </select>
        </div>
        @endif
        <div class="col-md-3">
          <label class="form-label fw-bold small text-muted text-uppercase mb-2">Brand</label>
          <select name="brand" class="form-select form-select-hitech" onchange="this.form.submit()">
            <option value="">All brands</option>
            @foreach($brands as $b)<option value="{{ $b }}" {{ $filters['brand'] === $b ? 'selected' : '' }}>{{ $b }}</option>@endforeach
          </select>
        </div>
        <div class="col-md-2"><a href="{{ route('performance.ninety-day-goals.dashboard') }}" class="btn btn-hitech-cancel w-100 rounded-pill">Clear</a></div>
      </form>
    </div>
  </div>

  @if($c['goals'] === 0)
    <div class="hitech-card mb-6"><div class="card-body p-5 text-center text-muted">
      No goals match these filters yet. Customers show up here as soon as they are added to a round.
    </div></div>
  @else

  {{-- Summary --}}
  <div class="row g-3 mb-6">
    @php
      $cards = [
        ['bx-target-lock', 'Customers tracked', $c['goals'], ($seesMany ? $c['people'] . ' salespeople' : 'in your rounds')],
        ['bx-rupee', 'Monthly potential', $fmtL($c['potential']), '₹ lakh a month'],
        ['bx-flag', 'Round target', $fmtL($c['target']), 'potential × ' . $G::ROUND_MONTHS . ' months'],
        ['bx-trending-up', 'Sold so far', $fmtL($c['achieved']), 'in the Sales Report'],
        ['bx-pie-chart-alt-2', 'Achievement', $pctText($c['pct']), 'sold ÷ round target'],
        ['bx-cart-download', 'Turned into orders', $c['converted'], 'Trial or Regular order'],
        ['bx-time-five', 'Reported on time', $c['discipline'] === null ? '–' : $c['discipline'] . '%', $c['onTimeTotal'] . ' of ' . $c['dueTotal'] . ' Mondays'],
      ];
    @endphp
    @foreach($cards as [$icon, $label, $value, $sub])
      <div class="col-6 col-md-4 col-xl">
        <div class="ndg-kpi">
          <div class="ndg-kpi-l"><i class="bx {{ $icon }}"></i>{{ $label }}</div>
          <div class="ndg-kpi-v">{{ $value }}</div>
          <div class="ndg-kpi-s">{{ $sub }}</div>
        </div>
      </div>
    @endforeach
  </div>

  {{-- Rankings --}}
  <div class="row g-4 mb-6">
    <div class="col-xl-6">
      <div class="hitech-card h-100">
        <div class="card-body p-4 border-bottom"><div class="fw-bold text-dark">Top 10 customers by monthly potential</div><div class="small text-muted">The biggest targets, largest first</div></div>
        <div class="card-body p-4">
          @foreach($d['topCustomers'] as $i => $r)
            <div class="ndg-bar">
              <div class="ndg-bar-top">
                <span class="ndg-rank">{{ $i + 1 }}</span>
                <span class="ndg-bar-name" title="{{ $r->goal->client_name }}">{{ $r->goal->client_name }}<em>{{ $r->brand }}@if($seesMany) · {{ $name($r->user) }}@endif</em></span>
                <span class="ndg-bar-val">{{ $fmtL($r->potential) }} L</span>
              </div>
              <div class="ndg-track"><div class="ndg-fill" style="width:{{ round($r->potential / $maxCust * 100) }}%"></div></div>
            </div>
          @endforeach
        </div>
      </div>
    </div>
    <div class="col-xl-6">
      <div class="hitech-card h-100">
        <div class="card-body p-4 border-bottom"><div class="fw-bold text-dark">Top 10 brands by monthly potential</div><div class="small text-muted">Combined potential of every target customer for that brand</div></div>
        <div class="card-body p-4">
          @foreach($d['topBrands'] as $i => $r)
            <div class="ndg-bar">
              <div class="ndg-bar-top">
                <span class="ndg-rank">{{ $i + 1 }}</span>
                <span class="ndg-bar-name">{{ $r['brand'] }}<em>{{ $r['goals'] }} customers · sold {{ $fmtL($r['achieved']) }} L of {{ $fmtL($r['target']) }} L ({{ $pctText($r['pct']) }})</em></span>
                <span class="ndg-bar-val">{{ $fmtL($r['potential']) }} L</span>
              </div>
              <div class="ndg-track"><div class="ndg-fill" style="width:{{ round($r['potential'] / $maxBrand * 100) }}%"></div></div>
            </div>
          @endforeach
        </div>
      </div>
    </div>
    <div class="col-xl-6">
      <div class="hitech-card h-100">
        <div class="card-body p-4 border-bottom"><div class="fw-bold text-dark">Top 10 customers by sales achieved</div><div class="small text-muted">What has actually been sold in this round, from the Sales Report</div></div>
        <div class="card-body p-4">
          @forelse($d['topAchieved'] as $i => $r)
            <div class="ndg-bar">
              <div class="ndg-bar-top">
                <span class="ndg-rank">{{ $i + 1 }}</span>
                <span class="ndg-bar-name" title="{{ $r->goal->client_name }}">{{ $r->goal->client_name }}<em>{{ $r->brand }}@if($seesMany) · {{ $name($r->user) }}@endif · target {{ $fmtL($r->target) }} L</em></span>
                <span class="ndg-bar-val">{{ $fmtL($r->achieved) }} L<small style="color:{{ $pctColor($r->pct) }}">{{ $pctText($r->pct) }}</small></span>
              </div>
              <div class="ndg-track"><div class="ndg-fill" style="width:{{ round($r->achieved / $maxAch * 100) }}%;background:{{ $pctColor($r->pct) }}"></div></div>
            </div>
          @empty
            <div class="text-muted small">No sales recorded against these customers in this round yet.</div>
          @endforelse
        </div>
      </div>
    </div>
    <div class="col-xl-6">
      <div class="hitech-card h-100">
        <div class="card-body p-4 border-bottom"><div class="fw-bold text-dark">Where the customers stand</div><div class="small text-muted">Each customer's latest weekly status</div></div>
        <div class="card-body p-4">
          @foreach($d['statuses'] as $st => $n)
            @php [$bg, $fg] = $G::STATUS_COLORS[$st] ?? ['#e2e8f0', '#475569']; @endphp
            <div class="ndg-bar">
              <div class="ndg-bar-top">
                <span class="ndg-bar-name"><span class="ndg-chip" style="background:{{ $bg }};color:{{ $fg }}">{{ $st }}</span></span>
                <span class="ndg-bar-val">{{ $n }}<small>{{ $c['goals'] ? round($n / $c['goals'] * 100) : 0 }}%</small></span>
              </div>
              <div class="ndg-track"><div class="ndg-fill" style="width:{{ round($n / $maxStatus * 100) }}%;background:{{ $bg }}"></div></div>
            </div>
          @endforeach
        </div>
      </div>
    </div>
  </div>

  {{-- Per person --}}
  @if($seesMany)
  <div class="hitech-card mb-6">
    <div class="card-body p-4 border-bottom">
      <div class="fw-bold text-dark">Achievement by salesperson</div>
      <div class="small text-muted">Ranked by share of round target sold. Click a heading to sort by it.</div>
    </div>
    <div class="card-body p-0 table-responsive">
      <table class="table align-middle mb-0 ndg-people" id="ndgPeople">
        <thead>
          <tr>
            <th>#</th><th data-k="s">Salesperson</th>
            <th class="text-end" data-k="n">Customers</th>
            <th class="text-end" data-k="n">Monthly ₹ L</th>
            <th class="text-end" data-k="n">Target ₹ L</th>
            <th class="text-end" data-k="n">Sold ₹ L</th>
            <th data-k="n" style="min-width:170px">Achievement</th>
            <th class="text-end" data-k="n">Orders</th>
            <th class="text-end" data-k="n">On time</th>
            <th class="text-end" data-k="n">Late</th>
            <th class="text-end" data-k="n">Missing</th>
          </tr>
        </thead>
        <tbody>
        @foreach($d['users'] as $i => $u)
          <tr>
            <td class="text-muted">{{ $i + 1 }}</td>
            <td data-v="{{ $u['name'] }}"><a href="{{ route('performance.ninety-day-goals.index', ['view' => 'overview', 'user_id' => $u['id']]) }}" class="fw-semibold text-dark">{{ $u['name'] }}</a>
              <div class="small text-muted">{{ $u['department'] }}</div></td>
            <td class="text-end" data-v="{{ $u['goals'] }}">{{ $u['goals'] }}</td>
            <td class="text-end" data-v="{{ $u['potential'] }}">{{ $fmtL($u['potential']) }}</td>
            <td class="text-end" data-v="{{ $u['target'] }}">{{ $fmtL($u['target']) }}</td>
            <td class="text-end" data-v="{{ $u['achieved'] }}">{{ $fmtL($u['achieved']) }}</td>
            <td data-v="{{ $u['pct'] ?? -1 }}">
              <div class="d-flex align-items-center gap-2">
                <div class="ndg-track flex-grow-1" style="height:8px"><div class="ndg-fill" style="width:{{ min(100, (float) $u['pct']) }}%;background:{{ $pctColor($u['pct']) }}"></div></div>
                <b class="small" style="min-width:44px;text-align:right">{{ $pctText($u['pct']) }}</b>
              </div>
            </td>
            <td class="text-end" data-v="{{ $u['converted'] }}">{{ $u['converted'] }}</td>
            <td class="text-end" data-v="{{ $u['discipline'] ?? -1 }}">{{ $u['discipline'] === null ? '–' : $u['discipline'] . '%' }}</td>
            <td class="text-end" data-v="{{ $u['late'] }}">{{ $u['late'] ?: '·' }}</td>
            <td class="text-end" data-v="{{ $u['missing'] }}"><span class="{{ $u['missing'] ? 'text-danger fw-bold' : '' }}">{{ $u['missing'] ?: '·' }}</span></td>
          </tr>
        @endforeach
        </tbody>
      </table>
    </div>
  </div>
  @endif

  {{-- Week by week --}}
  <div class="hitech-card mb-6">
    <div class="card-body p-4 border-bottom"><div class="fw-bold text-dark">Statuses submitted, week by week</div><div class="small text-muted">Done out of expected for each Monday; red is how many arrived late</div></div>
    <div class="card-body p-4 d-flex flex-wrap gap-2">
      @foreach($d['strip'] as $w => $s)
        <div class="ndg-strip">
          <div class="fw-bold small">W{{ $w }}</div>
          <div class="small">{{ $s['done'] }} / {{ $s['total'] }}</div>
          @if($s['late'])<div class="text-danger" style="font-size:.65rem">{{ $s['late'] }} late</div>@endif
        </div>
      @endforeach
    </div>
  </div>

  <p class="small text-muted mb-6">
    Sold is taken from the Sales Report for the months the round covers. Round target is the customer's monthly potential for three months.
    "Orders" counts customers whose latest status is Trial Order or Regular Order.
  </p>
  @endif

  </div>
</div>

<style>
  .ndg-kpi { border: 1px solid #e2e8f0; border-radius: 12px; padding: 14px 16px; background: #fff; height: 100%; }
  .ndg-kpi-l { font-size: .68rem; font-weight: 700; text-transform: uppercase; letter-spacing: .04em; color: #64748b; display: flex; align-items: center; gap: 5px; }
  .ndg-kpi-l i { color: #007a7a; font-size: 1rem; }
  .ndg-kpi-v { font-size: 1.6rem; font-weight: 800; color: #0f172a; line-height: 1.25; margin-top: 4px; font-variant-numeric: tabular-nums; }
  .ndg-kpi-s { font-size: .7rem; color: #94a3b8; }
  .ndg-bar { margin-bottom: 14px; }
  .ndg-bar:last-child { margin-bottom: 0; }
  .ndg-bar-top { display: flex; align-items: center; gap: 10px; margin-bottom: 5px; }
  .ndg-rank { width: 22px; height: 22px; flex: none; border-radius: 6px; background: #f0f7f6; color: #007a7a; font-size: .72rem; font-weight: 800; display: inline-flex; align-items: center; justify-content: center; }
  .ndg-bar-name { flex: 1; min-width: 0; font-weight: 600; font-size: .86rem; color: #0f172a; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
  .ndg-bar-name em { display: block; font-style: normal; font-weight: 400; font-size: .7rem; color: #94a3b8; overflow: hidden; text-overflow: ellipsis; }
  .ndg-bar-val { font-weight: 800; font-size: .88rem; font-variant-numeric: tabular-nums; white-space: nowrap; text-align: right; }
  .ndg-bar-val small { display: block; font-size: .68rem; font-weight: 700; color: #94a3b8; }
  .ndg-track { height: 7px; background: #eef2f5; border-radius: 99px; overflow: hidden; }
  .ndg-fill { height: 100%; background: #007a7a; border-radius: 99px; min-width: 2px; }
  .ndg-chip { display: inline-block; padding: 3px 9px; border-radius: 6px; font-size: .72rem; font-weight: 700; }
  .ndg-strip { border: 1px solid #e2e8f0; border-radius: 10px; padding: 8px 12px; min-width: 78px; text-align: center; background: #fbfcfd; }
  .ndg-people th[data-k] { cursor: pointer; white-space: nowrap; }
  .ndg-people th[data-k]:hover { color: #007a7a; }
  .ndg-people td, .ndg-people th { white-space: nowrap; }
</style>
@endsection

@section('page-script')
<script>
  (function () {
    var table = document.getElementById('ndgPeople');
    if (!table) return;
    var body = table.tBodies[0];
    var dir = {};
    table.tHead.querySelectorAll('th[data-k]').forEach(function (th) {
      th.addEventListener('click', function () {
        var col = th.cellIndex, text = th.dataset.k === 's';
        dir[col] = dir[col] === 1 ? -1 : 1;
        Array.prototype.slice.call(body.rows).sort(function (a, b) {
          var x = a.cells[col].dataset.v, y = b.cells[col].dataset.v;
          return (text ? x.localeCompare(y) : parseFloat(x) - parseFloat(y)) * dir[col] * (text ? 1 : -1);
        }).forEach(function (r, i) { body.appendChild(r); r.cells[0].textContent = i + 1; });
      });
    });
  })();
</script>
@endsection
