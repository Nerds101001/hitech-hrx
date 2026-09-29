@extends('layouts/layoutMaster')

@section('title', 'Calls Dashboard')

@section('content')
<div class="layout-full-width">

  <div class="d-flex flex-wrap align-items-center justify-content-between gap-3 mb-6 mx-4">
    <div>
      <h3 class="fw-extrabold mb-0 text-dark">Calls Dashboard</h3>
      <p class="text-muted mb-0 small">Every call HRX has tracked — placed via the click-to-call button, or reported by a vendor for a call placed elsewhere.</p>
    </div>
    <nav aria-label="breadcrumb">
      <ol class="breadcrumb mb-0">
        <li class="breadcrumb-item"><a href="{{ url('/') }}">Dashboard</a></li>
        <li class="breadcrumb-item active">Calls</li>
      </ol>
    </nav>
  </div>

  <div class="px-4">

    {{-- Dialer — places a real call through the same API the report below
         reads from. Numbers ending 7/8/9 hit the mock's busy/no-answer/
         invalid_number branches; anything else connects. --}}
    <div class="hitech-card-white border-0 shadow-sm rounded-4 p-4 mb-4">
      <h6 class="fw-bold mb-3">Place a Call</h6>
      <div class="row g-2 align-items-end">
        <div class="col-md-4">
          <label class="form-label small fw-semibold mb-1">Number to call</label>
          <input type="text" id="dialerTo" class="form-control" placeholder="9876543210 — end in 7/8/9 to test busy/no-answer/invalid">
        </div>
        <div class="col-md-2">
          <button type="button" id="dialerCallBtn" class="btn btn-hitech-primary w-100"><i class="bx bx-phone-call"></i> Call</button>
        </div>
      </div>

      <div id="dialerActive" class="d-none mt-4 pt-3 border-top">
        <div class="d-flex flex-wrap align-items-center gap-3 mb-3">
          <span class="badge rounded-pill" id="dialerStatusBadge">—</span>
          <span class="small text-muted" id="dialerCallId"></span>
        </div>
        <div class="d-flex flex-wrap gap-2 mb-3">
          <button type="button" id="dialerHangupBtn" class="btn btn-outline-danger btn-sm"><i class="bx bx-phone-off"></i> Hangup</button>
          <button type="button" id="dialerSimulateBtn" class="btn btn-outline-warning btn-sm"><i class="bx bx-user"></i> Simulate customer hangup</button>
        </div>
        <div id="dialerDispositionRow" class="d-none row g-2 align-items-end">
          <div class="col-md-4">
            <label class="form-label small fw-semibold mb-1">Disposition</label>
            <input type="text" id="dialerDispCode" class="form-control form-control-sm" placeholder="e.g. interested, callback, not-reachable">
          </div>
          <div class="col-md-2">
            <button type="button" id="dialerDispBtn" class="btn btn-sm btn-outline-primary w-100">Save</button>
          </div>
        </div>
        <div id="dialerRecordingRow" class="d-none mt-3 small"></div>
      </div>
    </div>

    {{-- Stat tiles --}}
    <div class="row g-3 mb-4">
      @php
        $tiles = [
          ['label' => 'Total calls', 'value' => number_format($stats['total']), 'icon' => 'bx-phone', 'color' => '#0f172a'],
          ['label' => 'Via HRX', 'value' => number_format($stats['hrx']), 'icon' => 'bx-mouse', 'color' => '#0e6b62'],
          ['label' => 'Elsewhere', 'value' => number_format($stats['external']), 'icon' => 'bx-phone-call', 'color' => '#b45309'],
          ['label' => 'Answered', 'value' => number_format($stats['answered']), 'icon' => 'bx-phone-incoming', 'color' => '#15803d'],
          ['label' => 'Answer rate', 'value' => $stats['answer_rate'] . '%', 'icon' => 'bx-trending-up', 'color' => $stats['answer_rate'] >= 50 ? '#15803d' : '#b45309'],
          ['label' => 'Ended', 'value' => number_format($stats['ended']), 'icon' => 'bx-phone-off', 'color' => '#64748b'],
        ];
      @endphp
      @foreach($tiles as $t)
        <div class="col-6 col-md-4 col-xl-2">
          <div class="hitech-card-white border-0 shadow-sm rounded-4 p-3 h-100">
            <div class="d-flex align-items-center gap-2 mb-1">
              <i class="bx {{ $t['icon'] }}" style="font-size:1.1rem; color:{{ $t['color'] }};"></i>
              <span class="text-muted small">{{ $t['label'] }}</span>
            </div>
            <div style="font-size:1.6rem; font-weight:800; color:{{ $t['color'] }};">{{ $t['value'] }}</div>
          </div>
        </div>
      @endforeach
    </div>

    {{-- Filters --}}
    <div class="hitech-card-white border-0 shadow-sm rounded-4 p-3 mb-4">
      <form method="GET" class="row g-2 align-items-end">
        <div class="col-6 col-md-2">
          <label class="form-label small fw-semibold mb-1">Origin</label>
          <select name="origin" class="form-select form-select-sm">
            <option value="">All</option>
            <option value="hrx" @selected(($filters['origin'] ?? '') === 'hrx')>Via HRX</option>
            <option value="external" @selected(($filters['origin'] ?? '') === 'external')>Elsewhere</option>
          </select>
        </div>
        <div class="col-6 col-md-2">
          <label class="form-label small fw-semibold mb-1">Status</label>
          <select name="status" class="form-select form-select-sm">
            <option value="">All</option>
            @foreach(['queued','ringing','connected','busy','no_answer','invalid_number','failed','ended'] as $s)
              <option value="{{ $s }}" @selected(($filters['status'] ?? '') === $s)>{{ ucfirst(str_replace('_',' ',$s)) }}</option>
            @endforeach
          </select>
        </div>
        <div class="col-6 col-md-3">
          <label class="form-label small fw-semibold mb-1">Agent</label>
          <select name="agent_id" class="form-select form-select-sm">
            <option value="">All</option>
            @foreach($agents as $a)
              <option value="{{ $a->id }}" @selected((string)($filters['agent_id'] ?? '') === (string)$a->id)>{{ trim($a->first_name.' '.$a->last_name) }}</option>
            @endforeach
          </select>
        </div>
        <div class="col-6 col-md-2">
          <label class="form-label small fw-semibold mb-1">From</label>
          <input type="date" name="from" value="{{ $filters['from'] ?? '' }}" class="form-control form-control-sm">
        </div>
        <div class="col-6 col-md-2">
          <label class="form-label small fw-semibold mb-1">To</label>
          <input type="date" name="to" value="{{ $filters['to'] ?? '' }}" class="form-control form-control-sm">
        </div>
        <div class="col-6 col-md-1 d-flex gap-2">
          <button type="submit" class="btn btn-hitech-primary btn-sm w-100"><i class="bx bx-filter-alt"></i></button>
        </div>
      </form>
    </div>

    {{-- Table --}}
    <div class="hitech-card-white border-0 shadow-sm rounded-4 overflow-hidden">
      <div class="table-responsive">
        <table class="table mb-0" id="callsTable">
          <thead>
            <tr>
              <th>Call</th>
              <th>Agent</th>
              <th>From</th>
              <th>To</th>
              <th>Status</th>
              <th>Outcome</th>
              <th>Origin</th>
              <th>Ring</th>
              <th>Talk time</th>
              <th>Agent disposition</th>
              <th>Started</th>
            </tr>
          </thead>
          <tbody>
            @forelse($calls as $c)
              @php
                $toneMap = [
                  'connected' => ['#dcfce7', '#15803d'], 'ended' => ['#f1f5f9', '#475569'],
                  'busy' => ['#fef3c7', '#92400e'], 'no_answer' => ['#fef3c7', '#92400e'],
                  'invalid_number' => ['#fee2e2', '#991b1b'], 'failed' => ['#fee2e2', '#991b1b'],
                  'ringing' => ['#e0e7ff', '#3730a3'], 'queued' => ['#e0e7ff', '#3730a3'],
                ];
                $tone = $toneMap[$c->status->value] ?? ['#f1f5f9', '#475569'];
                // Same tone family as $toneMap, keyed on CentroVision's own
                // wording instead of our normalised status -- both describe
                // the same outcome, just in two different vocabularies.
                $vendorToneMap = [
                  'Answered By Agent'   => ['#dcfce7', '#15803d'],
                  'Ringing - No Answer' => ['#fef3c7', '#92400e'],
                  'Busy'                => ['#fef3c7', '#92400e'],
                  'Rejected By Switch'  => ['#fee2e2', '#991b1b'],
                ];
                $vendorTone = $vendorToneMap[$c->vendor_disposition] ?? ['#f1f5f9', '#475569'];
              @endphp
              <tr>
                <td class="small text-muted" title="{{ $c->call_uuid }}">{{ \Illuminate\Support\Str::limit($c->call_uuid, 8, '…') }}</td>
                <td>{{ $c->agent ? trim($c->agent->first_name.' '.$c->agent->last_name) : '—' }}</td>
                <td>{{ $c->from_number }}</td>
                <td>{{ $c->to_number }}</td>
                <td><span class="badge rounded-pill" style="background:{{ $tone[0] }}; color:{{ $tone[1] }};">{{ ucfirst(str_replace('_',' ',$c->status->value)) }}</span></td>
                <td>
                  @if($c->vendor_disposition)
                    <span class="badge rounded-pill" style="background:{{ $vendorTone[0] }}; color:{{ $vendorTone[1] }};">{{ $c->vendor_disposition }}</span>
                  @else
                    <span class="text-muted">—</span>
                  @endif
                </td>
                <td>
                  @if($c->origin?->value === 'hrx')
                    <span class="badge badge-brand-soft rounded-pill">HRX</span>
                  @else
                    <span class="badge bg-label-warning rounded-pill">Elsewhere</span>
                  @endif
                </td>
                <td>{{ $c->ring_seconds !== null ? gmdate('i:s', $c->ring_seconds) : '—' }}</td>
                <td>{{ $c->duration !== null ? gmdate('i:s', $c->duration) : '—' }}</td>
                <td>{{ $c->disposition_code ?? '—' }}</td>
                <td class="small text-muted">{{ $c->created_at->format('d M, h:i A') }}</td>
              </tr>
            @empty
              <tr><td colspan="11" class="text-center py-5 text-muted">No calls match these filters.</td></tr>
            @endforelse
          </tbody>
        </table>
      </div>
    </div>

    <div class="mt-3">{{ $calls->links() }}</div>

  </div>
</div>

<style>
  /* hitech-portal.scss has a later .table thead th rule with !important that
     silently wins over a plain inline style — matched at the same tier here
     with an ID-scoped selector, learned the hard way on the KPI table. */
  #callsTable thead th {
    background-color: #0f766e !important;
    color: #fff !important;
    border: none !important;
    font-weight: 700 !important;
    font-size: .78rem !important;
    text-transform: uppercase !important;
    letter-spacing: .03em !important;
    padding: .85rem 1rem !important;
  }
</style>

<script>
(function () {
  var currentCallId = null;
  var pollTimer = null;
  var TERMINAL = ['ended', 'busy', 'no_answer', 'invalid_number', 'failed'];
  var TONE = {
    connected: ['#dcfce7', '#15803d'], ended: ['#f1f5f9', '#475569'],
    busy: ['#fef3c7', '#92400e'], no_answer: ['#fef3c7', '#92400e'],
    invalid_number: ['#fee2e2', '#991b1b'], failed: ['#fee2e2', '#991b1b'],
    ringing: ['#e0e7ff', '#3730a3'], queued: ['#e0e7ff', '#3730a3'],
  };
  var el = function (id) { return document.getElementById(id); };

  function render(call) {
    el('dialerActive').classList.remove('d-none');
    el('dialerCallId').textContent = 'Call ID: ' + call.call_id;

    var tone = TONE[call.status] || ['#f1f5f9', '#475569'];
    var badge = el('dialerStatusBadge');
    badge.style.background = tone[0];
    badge.style.color = tone[1];
    badge.textContent = call.status.replace('_', ' ');

    var isDone = TERMINAL.indexOf(call.status) !== -1;
    el('dialerHangupBtn').disabled = isDone;
    el('dialerSimulateBtn').disabled = isDone;
    el('dialerDispositionRow').classList.toggle('d-none', !isDone);

    // The URL comes from the telephony provider's callback: only a real
    // http(s) link is shown, and it is set as text/href, never parsed as HTML
    // (a javascript: URL or markup in it used to run on click / on render).
    if (call.recording_url && /^https?:\/\//i.test(call.recording_url)) {
      var row = el('dialerRecordingRow');
      row.classList.remove('d-none');
      row.textContent = 'Recording: ';
      var a = document.createElement('a');
      a.href = call.recording_url; a.target = '_blank'; a.rel = 'noopener';
      a.textContent = call.recording_url;
      row.appendChild(a);
    } else {
      el('dialerRecordingRow').classList.add('d-none');
    }

    if (isDone && pollTimer) { clearInterval(pollTimer); pollTimer = null; }
  }

  function poll() {
    if (!currentCallId) return;
    fetch('/user/api/v1/calls/' + currentCallId).then(function (r) { return r.json(); }).then(render);
  }

  el('dialerCallBtn').addEventListener('click', function () {
    var to = el('dialerTo').value.trim();
    if (!/^\d{10,15}$/.test(to)) { alert('Enter a number, 10 to 15 digits.'); return; }

    this.disabled = true;
    var btn = this;
    fetch('/user/api/v1/calls', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': '{{ csrf_token() }}' },
      body: JSON.stringify({ to: to }),
    })
      .then(function (r) { return r.json(); })
      .then(function (call) {
        currentCallId = call.call_id;
        render(call);
        if (pollTimer) clearInterval(pollTimer);
        pollTimer = setInterval(poll, 2000);
      })
      .finally(function () { btn.disabled = false; });
  });

  el('dialerHangupBtn').addEventListener('click', function () {
    if (!currentCallId) return;
    fetch('/user/api/v1/calls/' + currentCallId + '/hangup', { method: 'POST', headers: { 'X-CSRF-TOKEN': '{{ csrf_token() }}' } })
      .then(function (r) { return r.json(); }).then(render);
  });

  el('dialerSimulateBtn').addEventListener('click', function () {
    if (!currentCallId) return;
    fetch('/user/api/v1/calls/' + currentCallId + '/simulate-customer-hangup', { method: 'POST', headers: { 'X-CSRF-TOKEN': '{{ csrf_token() }}' } })
      .then(function (r) { return r.json(); }).then(render);
  });

  el('dialerDispBtn').addEventListener('click', function () {
    if (!currentCallId) return;
    var code = el('dialerDispCode').value.trim();
    if (!code) return;
    fetch('/user/api/v1/calls/' + currentCallId + '/disposition', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': '{{ csrf_token() }}' },
      body: JSON.stringify({ code: code }),
    })
      .then(function (r) { return r.json(); })
      .then(function () { setTimeout(function () { location.reload(); }, 600); });
  });
})();
</script>
@endsection
