@php $configData = Helper::appClasses(); @endphp
@extends('layouts/contentNavbarLayout')

@section('title', '90-Day Goals — New Program')

@section('content')
<div class="layout-full-width animate__animated animate__fadeIn">

  <div class="d-flex flex-wrap align-items-center justify-content-between gap-3 mb-4 mx-4">
    <div>
      <a href="{{ route('performance.ninety-day-goals.index', ['view' => 'overview']) }}" class="text-muted small d-inline-flex align-items-center gap-1 mb-2"><i class="bx bx-arrow-back"></i> Back to Goals</a>
      <h3 class="fw-extrabold mb-1 text-dark">New 90-Day Goals program</h3>
      <p class="text-muted mb-0 small">Give it a name and its own start and end date, then pick who's in it. Several programs can run at once, each on its own timeline — anyone already mid-round is skipped, not replaced.</p>
    </div>
  </div>

  <div class="px-4">

  <div class="hitech-card mb-6">
    <div class="card-body p-4">
      <div class="row g-3">
        <div class="col-md-6">
          <label class="form-label fw-bold small text-muted text-uppercase mb-2">Program name</label>
          <input type="text" id="ndgPName" class="form-control" placeholder="e.g. Q3 North Zone Push" maxlength="150">
        </div>
        <div class="col-md-3">
          <label class="form-label fw-bold small text-muted text-uppercase mb-2">Start date</label>
          <input type="date" id="ndgPStart" class="form-control">
        </div>
        <div class="col-md-3">
          <label class="form-label fw-bold small text-muted text-uppercase mb-2">End date</label>
          <input type="date" id="ndgPEnd" class="form-control">
        </div>
      </div>
      <div class="small text-muted mt-2">Everyone invited gets a task to add their target customers, due 3 days after the start date — and a notification and email the moment the program is created.</div>
    </div>
  </div>

  <div class="hitech-card mb-6">
    <div class="card-body p-4 border-bottom d-flex flex-wrap align-items-center justify-content-between gap-2">
      <div>
        <div class="fw-bold text-dark">Who's in it</div>
        <div class="small text-muted">Every salesperson is ticked by default — untick anyone to leave them out.</div>
      </div>
      <div class="d-flex gap-2">
        <button type="button" class="btn btn-hitech-cancel rounded-pill px-3" id="ndgPAll">Select all</button>
        <button type="button" class="btn btn-hitech-cancel rounded-pill px-3" id="ndgPNone">Select none</button>
      </div>
    </div>
    <div class="card-body p-4">
      <div class="ndg-p-people">
        @foreach($people as $p)
          <label class="ndg-p-person">
            <input type="checkbox" class="ndg-p-check" value="{{ $p->id }}" checked>
            <span>{{ $p->first_name }} {{ $p->last_name }}<em>{{ optional($p->department)->name }}</em></span>
          </label>
        @endforeach
      </div>
    </div>
  </div>

  <div class="d-flex align-items-center gap-3 mb-6">
    <button type="button" class="btn-hitech rounded-pill px-5 py-2" id="ndgPSave">Create program and invite</button>
    <span class="small text-danger" id="ndgPMsg"></span>
  </div>

  </div>
</div>

<style>
  .ndg-p-people { display: grid; grid-template-columns: repeat(auto-fill, minmax(220px, 1fr)); gap: 4px 14px; max-height: 50vh; overflow: auto; }
  .ndg-p-person { display: flex; align-items: center; gap: 8px; padding: 6px 4px; cursor: pointer; }
  .ndg-p-person span { font-size: .86rem; color: #0f172a; }
  .ndg-p-person em { display: block; font-style: normal; font-size: .68rem; color: #94a3b8; }
</style>
@endsection

@section('page-script')
<script>
(function () {
  var CSRF = '{{ csrf_token() }}';
  var URL = '{{ route('performance.ninety-day-goals.programs.store') }}';
  var BACK = '{{ route('performance.ninety-day-goals.index', ['view' => 'overview']) }}';

  document.getElementById('ndgPAll').addEventListener('click', function () { document.querySelectorAll('.ndg-p-check').forEach(function (c) { c.checked = true; }); });
  document.getElementById('ndgPNone').addEventListener('click', function () { document.querySelectorAll('.ndg-p-check').forEach(function (c) { c.checked = false; }); });

  document.getElementById('ndgPSave').addEventListener('click', function () {
    var name = document.getElementById('ndgPName').value.trim();
    var starts = document.getElementById('ndgPStart').value, ends = document.getElementById('ndgPEnd').value;
    var ids = Array.prototype.filter.call(document.querySelectorAll('.ndg-p-check'), function (c) { return c.checked; }).map(function (c) { return parseInt(c.value, 10); });
    var msg = document.getElementById('ndgPMsg'), btn = this;

    if (!name) { msg.textContent = 'Give the program a name.'; return; }
    if (!starts || !ends) { msg.textContent = 'Pick a start and end date.'; return; }
    if (ends <= starts) { msg.textContent = 'The end date must be after the start date.'; return; }
    if (!ids.length) { msg.textContent = 'Pick at least one salesperson.'; return; }

    msg.textContent = ''; btn.disabled = true;
    fetch(URL, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': CSRF },
      body: JSON.stringify({ name: name, starts_on: starts, ends_on: ends, user_ids: ids }),
    }).then(function (r) { return r.json().then(function (j) { j._status = r.status; return j; }); })
      .then(function (j) {
        btn.disabled = false;
        if (!j.success) { msg.textContent = j.message || (j.errors ? Object.values(j.errors).join(' ') : 'Could not create the program.'); return; }
        alert(j.message);
        location.href = BACK;
      }).catch(function () { btn.disabled = false; msg.textContent = 'Request failed. Try again.'; });
  });
})();
</script>
@endsection
