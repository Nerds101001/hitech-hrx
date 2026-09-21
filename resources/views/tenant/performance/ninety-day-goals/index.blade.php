@php $configData = Helper::appClasses(); @endphp
@extends('layouts/contentNavbarLayout')

@section('title', '90-Day Goals')



@section('content')
@php
  $G = \App\Services\NinetyDayGoalService::class;

  /** A week's cell: the status as a coloured chip with its comment written underneath; "late" marks a Monday answered after its day. */
  $chip = function ($checkin, $due, $today) use ($G) {
      if ($checkin && $checkin->status) {
          [$bg, $fg] = $G::STATUS_COLORS[$checkin->status] ?? ['#64748b', '#ffffff'];
          $late = $checkin->is_late ? '<i class="ndg-late">late, sent ' . e(optional($checkin->submitted_at)->format('d M')) . '</i>' : '';
          $note = $checkin->comment ? '<div class="ndg-note">' . e($checkin->comment) . '</div>' : '';
          return '<span class="ndg-chip" style="background:' . $bg . ';color:' . $fg . '">' . e($checkin->status) . '</span>' . $late . $note;
      }
      if ($checkin && $checkin->due_on->lte($today)) {
          return '<span class="ndg-chip ndg-missing" title="Due ' . $checkin->due_on->format('D d M') . ', not submitted">not sent</span>';
      }
      return '<span class="ndg-none">·</span>';
  };
  $fmtL = fn ($v) => rtrim(rtrim(number_format((float) $v, 2), '0'), '.');
@endphp
<div class="layout-full-width animate__animated animate__fadeIn">

  <div class="d-flex flex-wrap align-items-center justify-content-between gap-3 mb-6 mx-4">
    <div>
      <h3 class="fw-extrabold mb-1 text-dark">90-Day Goals</h3>
      <p class="text-muted mb-0 small">
        @if($mode === 'mine')
          Pick target customers from your own Sales Pipeline and Sales Report, then give each a status every Monday for 13 weeks.
        @else
          Every salesperson's target customers and their status for each Monday, W0 to W12.
        @endif
      </p>
    </div>
    <div class="d-flex gap-3 flex-wrap">
      @if($canSwitch)
        <a href="{{ route('performance.ninety-day-goals.index', ['view' => $mode === 'mine' ? 'overview' : 'mine']) }}"
           class="btn btn-hitech-cancel shadow-sm rounded-pill px-4 d-flex align-items-center gap-2">
          <i class="bx bx-{{ $mode === 'mine' ? 'group' : 'user' }} fs-5"></i><span class="fw-bold">{{ $mode === 'mine' ? 'Everyone' : 'My goals' }}</span>
        </a>
      @endif
      @if($mode === 'mine')
        <button type="button" class="btn-hitech shadow-sm rounded-pill px-5 d-flex align-items-center gap-2" data-bs-toggle="modal" data-bs-target="#addCustomersModal">
          <i class="bx bx-plus-circle fs-5"></i><span class="fw-bold">Add customers</span>
        </button>
      @endif
    </div>
  </div>

  @php $tab = 'goals'; $seesMany = $mode === 'overview' || $canSwitch; @endphp
  @include('tenant.performance.ninety-day-goals._tabs')

  <div class="px-4">

@if($mode === 'mine')
  {{-- ───────────────────────── MY GOALS ───────────────────────── --}}
  @php $weekNo = $week; $pct = round(($weekNo + 1) / $weeks * 100); @endphp
  <div class="hitech-card mb-6">
    <div class="card-body p-4 d-flex flex-wrap align-items-center justify-content-between gap-3">
      <div>
        <div class="fw-bold text-dark">Round {{ $round->round_no }}
          <span class="text-muted fw-normal">· {{ $round->starts_on->format('d M Y') }} to {{ $round->ends_on->format('d M Y') }}</span></div>
        <div class="small text-muted">Week {{ $weekNo }} of {{ $weeks - 1 }} · a new round starts by itself on {{ $round->ends_on->copy()->addDay()->format('D d M') }}</div>
      </div>
      <div style="min-width:220px;flex:1;max-width:420px">
        <div class="progress" style="height:8px"><div class="progress-bar" style="width:{{ $pct }}%;background:#007a7a"></div></div>
      </div>
    </div>
  </div>

  {{-- Statuses owed --}}
  @foreach($pending as $wk => $rows)
    @php
      $due = $rows->first()->due_on;
      $isLate = $today->gt($due);
    @endphp
    <div class="hitech-card mb-6 ndg-week" data-week="{{ $wk }}" data-late="{{ $isLate ? 1 : 0 }}">
      <div class="card-body p-4 border-bottom d-flex flex-wrap align-items-center justify-content-between gap-2">
        <h6 class="fw-bold mb-0 text-dark">W{{ $wk }} status
          <span class="text-muted fw-normal">· due {{ $due->format('D d M') }}</span></h6>
        @if($isLate)
          <span class="badge badge-hitech badge-hitech-danger">Monday has passed: pick a status and write a comment for each</span>
        @else
          <span class="badge badge-hitech badge-brand-soft">Due today</span>
        @endif
      </div>
      <div class="card-body p-0 table-responsive">
        <table class="table align-middle mb-0">
          <thead><tr><th style="width:24%">Customer</th><th style="width:22%">Status</th><th>Comment{{ $isLate ? ' (needed)' : '' }}</th></tr></thead>
          <tbody>
          @foreach($rows as $c)
            <tr data-goal="{{ $c->goal_id }}">
              <td class="fw-semibold">{{ $c->goal->client_name }}<div class="small text-muted fw-normal">{{ $c->goal->product }}</div></td>
              <td>
                <select class="form-select form-select-hitech ndg-status">
                  <option value="">Choose…</option>
                  @foreach($statuses as $s)<option value="{{ $s }}">{{ $s }}</option>@endforeach
                </select>
              </td>
              <td><input type="text" class="form-control ndg-comment" maxlength="1000" placeholder="{{ $isLate ? 'Why was this not sent on Monday?' : 'Optional note' }}"></td>
            </tr>
          @endforeach
          </tbody>
        </table>
      </div>
      <div class="card-body p-4 d-flex align-items-center justify-content-between gap-3">
        <span class="small text-danger ndg-msg"></span>
        <button type="button" class="btn-hitech shadow-sm rounded-pill px-5 ndg-submit">Submit W{{ $wk }}</button>
      </div>
    </div>
  @endforeach

  @if($goals->isEmpty())
    <div class="hitech-card"><div class="card-body p-5 text-center">
      <h6 class="fw-bold text-dark mb-2">No customers in this round yet</h6>
      <p class="text-muted mb-4">Search your Sales Pipeline and Sales Report and add the customers you are going after.</p>
      <button type="button" class="btn-hitech shadow-sm rounded-pill px-5" data-bs-toggle="modal" data-bs-target="#addCustomersModal">Add customers</button>
    </div></div>
  @else
    <div class="hitech-card">
      <div class="card-body p-0 table-responsive">
        <table class="table align-middle mb-0 ndg-grid">
          <thead>
            <tr>
              <th style="min-width:180px">Customer</th><th style="min-width:150px">Product</th><th>Brand</th><th style="min-width:130px">Goal</th>
              <th class="text-end">Monthly ₹ L</th>
              @for($w = 0; $w < $weeks; $w++)
                <th class="text-center ndg-wk {{ $w === $weekNo ? 'ndg-now' : '' }}">W{{ $w }}<div class="ndg-date">{{ $dates[$w]->format('d M') }}</div></th>
              @endfor
              <th></th>
            </tr>
          </thead>
          <tbody>
          @foreach($goals as $g)
            <tr>
              <td class="fw-semibold">{{ $g->client_name }}</td>
              <td>{{ $g->product }}</td>
              <td>{{ $g->brand }}</td>
              <td>{{ $g->goal }}</td>
              <td class="text-end fw-semibold">{{ $fmtL($g->monthly_potential) }}</td>
              @for($w = 0; $w < $weeks; $w++)
                <td class="text-center ndg-cell {{ $w === $weekNo ? 'ndg-now' : '' }}">{!! $chip($g->checkins->firstWhere('week_no', $w), $dates[$w], $today) !!}</td>
              @endfor
              <td class="text-end"><button type="button" class="btn btn-sm btn-icon icon-sophisticated text-danger ndg-remove" data-id="{{ $g->id }}" title="Remove"><i class="bx bx-trash"></i></button></td>
            </tr>
          @endforeach
          </tbody>
        </table>
      </div>
    </div>
  @endif

@else
  {{-- ───────────────────────── EVERYONE ───────────────────────── --}}
  <div class="row g-6 mb-6 mx-0">
    @php
      $cards = [
        ['card-teal', 'bx-group', $summary['people'] . ' / ' . $summary['staff'], 'Sales people with goals'],
        ['card-blue', 'bx-target-lock', $summary['goals'], 'Customers tracked'],
        ['card-amber', 'bx-rupee', $fmtL($summary['potential']), 'Monthly potential (₹ L)'],
        ['card-teal', 'bx-check-circle', $summary['thisWeekDone'] . ' / ' . $summary['thisWeekTotal'], 'This week submitted'],
        ['card-red', 'bx-time-five', $summary['overdue'], 'Overdue statuses'],
      ];
    @endphp
    @foreach($cards as [$cls, $icon, $val, $lab])
      <div class="col-xl col-md-4 col-6">
        <div class="hitech-stat-card dashboard-variant {{ $cls }} uniform-card">
          <div class="stat-card-header mb-2"><div class="stat-icon-wrap icon-{{ str_replace('card-', '', $cls) }}" style="width:38px;height:38px;font-size:1.2rem;"><i class="bx {{ $icon }}"></i></div></div>
          <div><h3 class="stat-value mb-0">{{ $val }}</h3><span class="stat-label">{{ $lab }}</span></div>
        </div>
      </div>
    @endforeach
  </div>

  {{-- Week by week --}}
  <div class="hitech-card mb-6">
    <div class="card-body p-4 border-bottom"><h6 class="fw-bold mb-0 text-dark">Statuses submitted, week by week</h6></div>
    <div class="card-body p-4 d-flex flex-wrap gap-2">
      @foreach($strip as $w => $s)
        <div class="ndg-strip">
          <div class="fw-bold">W{{ $w }}</div>
          <div class="small">{{ $s['done'] }} / {{ $s['total'] }}</div>
          @if($s['late'])<div class="small text-danger">{{ $s['late'] }} late</div>@endif
        </div>
      @endforeach
    </div>
    @if(!empty($summary['byStatus']))
      <div class="card-body p-4 pt-0 d-flex flex-wrap gap-2 align-items-center">
        <span class="small text-muted me-2">Latest status:</span>
        @foreach($statuses as $s)
          @if(!empty($summary['byStatus'][$s]))
            @php [$bg, $fg] = $G::STATUS_COLORS[$s]; @endphp
            <span class="ndg-chip" style="background:{{ $bg }};color:{{ $fg }}">{{ $s }} · {{ $summary['byStatus'][$s] }}</span>
          @endif
        @endforeach
      </div>
    @endif
  </div>

  {{-- Filters --}}
  <div class="hitech-card mb-6">
    <div class="card-body p-4">
      <form method="GET" action="{{ route('performance.ninety-day-goals.index') }}" class="row g-3 align-items-end">
        <input type="hidden" name="view" value="overview">
        <div class="col-md-3">
          <label class="form-label fw-bold small text-muted text-uppercase mb-2">Round</label>
          <select name="round" class="form-select form-select-hitech" onchange="this.form.submit()">
            <option value="current" {{ $filters['round'] === 'current' ? 'selected' : '' }}>Current rounds</option>
            <option value="all" {{ $filters['round'] === 'all' ? 'selected' : '' }}>All rounds, including closed</option>
          </select>
        </div>
        <div class="col-md-4">
          <label class="form-label fw-bold small text-muted text-uppercase mb-2">Salesperson</label>
          <select name="user_id" class="form-select form-select-hitech" onchange="this.form.submit()">
            <option value="">Everyone</option>
            @foreach($people as $p)<option value="{{ $p->id }}" {{ (int) $filters['user_id'] === $p->id ? 'selected' : '' }}>{{ $p->first_name }} {{ $p->last_name }}</option>@endforeach
          </select>
        </div>
        <div class="col-md-3">
          <label class="form-label fw-bold small text-muted text-uppercase mb-2">Latest status</label>
          <select name="status" class="form-select form-select-hitech" onchange="this.form.submit()">
            <option value="">Any</option>
            <option value="pending" {{ $filters['status'] === 'pending' ? 'selected' : '' }}>Nothing submitted yet</option>
            @foreach($statuses as $s)<option value="{{ $s }}" {{ $filters['status'] === $s ? 'selected' : '' }}>{{ $s }}</option>@endforeach
          </select>
        </div>
        <div class="col-md-2"><a href="{{ route('performance.ninety-day-goals.index', ['view' => 'overview']) }}" class="btn btn-hitech-cancel w-100 rounded-pill">Clear</a></div>
      </form>
    </div>
  </div>

  @forelse($groups as $uid => $rows)
    @php $u = $rows->first()->user; $round = $rows->first()->round; @endphp
    <div class="hitech-card mb-6">
      <div class="card-body p-4 border-bottom d-flex flex-wrap align-items-center justify-content-between gap-2">
        <div>
          <div class="fw-bold text-dark">{{ optional($u)->first_name }} {{ optional($u)->last_name }}
            <span class="text-muted fw-normal">· {{ optional(optional($u)->department)->name }}</span></div>
          <div class="small text-muted">
            @if($round) Round {{ $round->round_no }} · {{ $round->starts_on->format('d M') }} to {{ $round->ends_on->format('d M Y') }}@if($round->status !== 'active') · closed @endif
            @else Earlier goals, not part of a round @endif
          </div>
        </div>
        <div class="small"><b>{{ $rows->count() }}</b> customers · <b>{{ $fmtL($rows->sum('monthly_potential')) }}</b> ₹ L a month</div>
      </div>
      <div class="card-body p-0 table-responsive">
        <table class="table align-middle mb-0 ndg-grid">
          <thead>
            <tr>
              <th style="min-width:180px">Customer</th><th style="min-width:150px">Product</th><th>Brand</th><th style="min-width:130px">Goal</th><th class="text-end">Monthly ₹ L</th>
              @for($w = 0; $w < $weeks; $w++)<th class="text-center ndg-wk">W{{ $w }}@if($round)<div class="ndg-date">{{ $G::dueOn($round, $w)->format('d M') }}</div>@endif</th>@endfor
            </tr>
          </thead>
          <tbody>
          @foreach($rows as $g)
            <tr>
              <td class="fw-semibold">{{ $g->client_name }}</td>
              <td>{{ $g->product }}</td>
              <td>{{ $g->brand }}</td>
              <td>{{ $g->goal ?: $g->goal_type }}</td>
              <td class="text-end fw-semibold">{{ $fmtL($g->monthly_potential) }}</td>
              @for($w = 0; $w < $weeks; $w++)
                <td class="text-center ndg-cell">{!! $chip($g->checkins->firstWhere('week_no', $w), null, $today) !!}</td>
              @endfor
            </tr>
          @endforeach
          </tbody>
        </table>
      </div>
    </div>
  @empty
    <div class="hitech-card mb-6"><div class="card-body p-5 text-center text-muted">No goals match these filters.</div></div>
  @endforelse

  @if($notStarted->isNotEmpty() && $filters['round'] === 'current' && !$filters['user_id'] && !$filters['status'])
    <div class="hitech-card mb-6">
      <div class="card-body p-4">
        <h6 class="fw-bold text-dark mb-3">Have not added any customers yet ({{ $notStarted->count() }})</h6>
        <div class="d-flex flex-wrap gap-2">
          @foreach($notStarted as $p)<span class="badge badge-hitech badge-muted">{{ $p->first_name }} {{ $p->last_name }}</span>@endforeach
        </div>
      </div>
    </div>
  @endif
@endif

  </div>{{-- /px-4 --}}
</div>

@if($mode === 'mine')
{{-- Add customers: search the person's own Sales Pipeline and Sales Report, nothing typed in. --}}
<div class="modal fade" id="addCustomersModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">Add customers to this round</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">
        <input type="search" id="ndgSearch" class="form-control mb-3" placeholder="Search your pipeline and sales report by customer, product or brand…" autocomplete="off">
        <div id="ndgList" class="ndg-list"><div class="text-muted p-3">Loading your customers…</div></div>
      </div>
      <div class="modal-footer justify-content-between">
        <span class="small text-danger" id="ndgAddMsg"></span>
        <div class="d-flex gap-2">
          <button type="button" class="btn btn-hitech-cancel rounded-pill px-4" data-bs-dismiss="modal">Cancel</button>
          <button type="button" class="btn-hitech rounded-pill px-5" id="ndgAddBtn">Add selected</button>
        </div>
      </div>
    </div>
  </div>
</div>
@endif

<style>
  .ndg-grid th, .ndg-grid td { white-space: nowrap; }
  .ndg-wk { min-width: 170px; font-size: .78rem; }
  .ndg-date { font-weight: 400; color: #94a3b8; font-size: .68rem; }
  .ndg-now { background: #f0f7f6; }
  .ndg-chip { display: inline-block; position: relative; padding: 3px 9px; border-radius: 6px; font-size: .68rem; font-weight: 700; line-height: 1.25; }
  .ndg-chip.ndg-missing { background: #fff; color: #b42318; border: 1px dashed #f0a5a0; font-weight: 600; }
  .ndg-late { display: block; font-size: .6rem; font-style: normal; color: #b42318; font-weight: 700; margin-top: 2px; }
  .ndg-grid td.ndg-cell { white-space: normal; vertical-align: top; min-width: 170px; max-width: 220px; }
  .ndg-note { margin-top: 5px; font-size: .72rem; line-height: 1.35; color: #334155; text-align: left; overflow-wrap: anywhere; white-space: pre-line; }
  .ndg-none { color: #cbd5e1; }
  .ndg-strip { border: 1px solid #e2e8f0; border-radius: 10px; padding: 8px 12px; min-width: 78px; text-align: center; background: #fbfcfd; }
  .ndg-list .ndg-row { border: 1px solid #e2e8f0; border-radius: 10px; padding: 10px 12px; margin-bottom: 8px; }
  .ndg-list .ndg-row.on { border-color: #007a7a; background: #f0f7f6; }
  .ndg-list .ndg-extra { display: none; margin-top: 10px; }
  .ndg-list .ndg-row.on .ndg-extra { display: flex; }
  .ndg-src { font-size: .65rem; }
</style>
@endsection

@section('page-script')
<script>
(function () {
  var CSRF = '{{ csrf_token() }}';
  var URLS = {
    customers: '{{ route('performance.ninety-day-goals.pipeline-customers') }}',
    store: '{{ route('performance.ninety-day-goals.store') }}',
    checkin: '{{ route('performance.ninety-day-goals.checkin') }}',
    base: '{{ url('performance/ninety-day-goals') }}'
  };
  function post(url, method, body) {
    return fetch(url, { method: method, headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': CSRF }, body: body ? JSON.stringify(body) : undefined })
      .then(function (r) { return r.json().then(function (j) { j._status = r.status; return j; }); });
  }
  function esc(s) { return String(s == null ? '' : s).replace(/[&<>"']/g, function (c) { return {'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]; }); }

  // ── remove a customer ──
  document.querySelectorAll('.ndg-remove').forEach(function (b) {
    b.addEventListener('click', function () {
      if (!confirm('Remove this customer from your goals?')) { return; }
      post(URLS.base + '/' + b.getAttribute('data-id'), 'DELETE').then(function (j) {
        if (j.success) { location.reload(); } else { alert(j.message || 'Could not remove.'); }
      });
    });
  });

  // ── add customers from the pipeline ──
  var modal = document.getElementById('addCustomersModal');
  if (!modal) { return; }
  var list = document.getElementById('ndgList'), search = document.getElementById('ndgSearch'), addMsg = document.getElementById('ndgAddMsg');
  var customers = [], brands = [], loaded = false;

  function brandOptions(sel) {
    return '<option value="">Brand</option>' + brands.map(function (b) { return '<option' + (b === sel ? ' selected' : '') + '>' + esc(b) + '</option>'; }).join('');
  }
  function render() {
    var q = search.value.trim().toLowerCase();
    var rows = customers.filter(function (c) { return !q || (c.customer + ' ' + c.product + ' ' + c.brand).toLowerCase().indexOf(q) !== -1; });
    if (!customers.length) { list.innerHTML = '<div class="text-muted p-3">No customers left to add. Everything in your Sales Pipeline and Sales Report is already in this round, or you have none yet.</div>'; return; }
    if (!rows.length) { list.innerHTML = '<div class="text-muted p-3">Nothing matches that search.</div>'; return; }
    list.innerHTML = rows.map(function (c) {
      return '<div class="ndg-row' + (c.picked ? ' on' : '') + '" data-id="' + c.id + '">' +
        '<label class="d-flex align-items-center gap-2 mb-0" style="cursor:pointer"><input type="checkbox" class="form-check-input ndg-pick"' + (c.picked ? ' checked' : '') + '>' +
        '<span class="fw-semibold">' + esc(c.customer) + '</span> <span class="badge badge-hitech badge-muted ndg-src">' + esc(c.source) + '</span>' +
        '<span class="text-muted small ms-2">' + esc(c.product) + '</span></label>' +
        '<div class="ndg-extra gap-2 flex-wrap">' +
          '<input type="text" class="form-control form-control-sm ndg-product" style="max-width:220px" placeholder="Product" value="' + esc(c.pProduct != null ? c.pProduct : c.product) + '">' +
          '<select class="form-select form-select-sm ndg-brand" style="max-width:150px">' + brandOptions(c.pBrand != null ? c.pBrand : c.brand) + '</select>' +
          '<input type="text" class="form-control form-control-sm ndg-goal" style="max-width:220px" placeholder="Goal, for example Regular order" value="' + esc(c.pGoal != null ? c.pGoal : 'Order') + '">' +
          '<input type="number" step="0.01" min="0" class="form-control form-control-sm ndg-pot" style="max-width:150px" placeholder="Monthly ₹ lakh" value="' + esc(c.pPot != null ? c.pPot : '') + '">' +
        '</div></div>';
    }).join('');
  }
  function remember(row) {
    var c = customers.filter(function (x) { return String(x.id) === row.getAttribute('data-id'); })[0]; if (!c) { return; }
    c.picked = row.querySelector('.ndg-pick').checked;
    c.pProduct = row.querySelector('.ndg-product').value; c.pBrand = row.querySelector('.ndg-brand').value;
    c.pGoal = row.querySelector('.ndg-goal').value; c.pPot = row.querySelector('.ndg-pot').value;
  }
  list.addEventListener('input', function (e) { var r = e.target.closest('.ndg-row'); if (r) { remember(r); } });
  list.addEventListener('change', function (e) { var r = e.target.closest('.ndg-row'); if (r) { remember(r); r.classList.toggle('on', r.querySelector('.ndg-pick').checked); } });
  search.addEventListener('input', render);

  modal.addEventListener('shown.bs.modal', function () {
    if (loaded) { search.focus(); return; }
    fetch(URLS.customers, { headers: { 'Accept': 'application/json' } }).then(function (r) { return r.json(); }).then(function (j) {
      customers = j.customers || []; brands = j.brands || []; loaded = true; render(); search.focus();
    }).catch(function () { list.innerHTML = '<div class="text-danger p-3">Could not load your customers. Close this and try again.</div>'; });
  });

  document.getElementById('ndgAddBtn').addEventListener('click', function () {
    var picked = customers.filter(function (c) { return c.picked; });
    if (!picked.length) { addMsg.textContent = 'Tick at least one customer.'; return; }
    for (var i = 0; i < picked.length; i++) {
      if (!String(picked[i].pGoal != null ? picked[i].pGoal : 'Order').trim()) { addMsg.textContent = picked[i].customer + ': write the goal.'; return; }
      if (picked[i].pPot === undefined || picked[i].pPot === '' || isNaN(parseFloat(picked[i].pPot))) { addMsg.textContent = picked[i].customer + ': enter the monthly potential in lakh.'; return; }
    }
    addMsg.textContent = ''; this.disabled = true; var btn = this;
    post(URLS.store, 'POST', { customers: picked.map(function (c) {
      return { sales_pipeline_id: c.id, product: c.pProduct != null ? c.pProduct : c.product, brand: c.pBrand != null ? c.pBrand : c.brand, goal: c.pGoal != null ? c.pGoal : 'Order', monthly_potential: parseFloat(c.pPot) };
    }) }).then(function (j) {
      if (j.success) { location.reload(); return; }
      btn.disabled = false; addMsg.textContent = (j.message || 'Could not add.') + (j.errors ? ' ' + Object.values(j.errors).join(' ') : '');
    }).catch(function () { btn.disabled = false; addMsg.textContent = 'Request failed. Try again.'; });
  });
})();
</script>
@endsection
