@php $configData = Helper::appClasses(); @endphp
@extends('layouts/contentNavbarLayout')

@section('title', '90-Day Goals — New Program')


@section('vendor-style')
  @vite(['resources/assets/vendor/libs/sweetalert2/sweetalert2.scss'])
@endsection

@section('vendor-script')
  @vite(['resources/assets/vendor/libs/sweetalert2/sweetalert2.js'])
@endsection
@section('content')
<div class="layout-full-width animate__animated animate__fadeIn">

  <div class="d-flex flex-wrap align-items-center justify-content-between gap-3 mb-4 mx-4">
    <div>
      <a href="{{ route('performance.ninety-day-goals.programs.index') }}" class="text-muted small d-inline-flex align-items-center gap-1 mb-2"><i class="bx bx-arrow-back"></i> Back to Programs</a>
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
          <div class="small text-muted mt-1">Auto-filled to {{ $roundSpanDays }} days from the start — change it if this program should run longer or shorter.</div>
        </div>
      </div>
      <div class="small text-muted mt-2">Everyone invited gets a task to add their target customers, due 3 days after the start date — and a notification and email the moment the program is created.</div>
    </div>
  </div>

  <div class="hitech-card mb-6">
    <div class="card-body p-4 border-bottom">
      <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-3">
        <div>
          <div class="fw-bold text-dark">Who's in it</div>
          <div class="small text-muted" id="ndgPCount">Every salesperson is ticked by default — untick anyone to leave them out.</div>
        </div>
        <div class="d-flex gap-2">
          <button type="button" class="btn btn-hitech-cancel rounded-pill px-3" id="ndgPAll">Select all</button>
          <button type="button" class="btn btn-hitech-cancel rounded-pill px-3" id="ndgPNone">Select none</button>
        </div>
      </div>
      <div class="row g-2 align-items-center">
        <div class="col-md-6">
          <input type="search" id="ndgPSearch" class="form-control" placeholder="Search by name or department…">
        </div>
        <div class="col-md-6 d-flex gap-2">
          <button type="button" class="btn btn-hitech-cancel rounded-pill px-3" id="ndgPIncludeShown"><i class="bx bx-check-square"></i> Include shown</button>
          <button type="button" class="btn btn-hitech-cancel rounded-pill px-3" id="ndgPExcludeShown"><i class="bx bx-square"></i> Exclude shown</button>
        </div>
      </div>
    </div>
    <div class="card-body p-4">
      <div class="ndg-p-people">
        @foreach($people as $p)
          <label class="ndg-p-person" data-q="{{ strtolower($p->first_name . ' ' . $p->last_name . ' ' . optional($p->department)->name) }}">
            <input type="checkbox" class="ndg-p-check" value="{{ $p->id }}" checked>
            <span>{{ $p->first_name }} {{ $p->last_name }}<em>{{ optional($p->department)->name }}</em></span>
          </label>
        @endforeach
      </div>
      <div class="small text-muted mt-2 d-none" id="ndgPNoMatch">Nothing matches that search.</div>
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
  var BACK = '{{ route('performance.ninety-day-goals.programs.index') }}';

  var ROUND_SPAN_DAYS = {{ (int) $roundSpanDays }};
  var people = Array.prototype.slice.call(document.querySelectorAll('.ndg-p-person'));
  var countEl = document.getElementById('ndgPCount');

  function updateCount() {
    var total = people.length, checked = people.filter(function (p) { return p.querySelector('.ndg-p-check').checked; }).length;
    countEl.textContent = checked + ' of ' + total + ' selected.';
  }
  document.querySelectorAll('.ndg-p-check').forEach(function (c) { c.addEventListener('change', updateCount); });
  updateCount();

  document.getElementById('ndgPAll').addEventListener('click', function () { document.querySelectorAll('.ndg-p-check').forEach(function (c) { c.checked = true; }); updateCount(); });
  document.getElementById('ndgPNone').addEventListener('click', function () { document.querySelectorAll('.ndg-p-check').forEach(function (c) { c.checked = false; }); updateCount(); });

  // ── search, and include/exclude just what's showing ──
  var search = document.getElementById('ndgPSearch'), noMatch = document.getElementById('ndgPNoMatch');
  function visiblePeople() {
    var q = search.value.trim().toLowerCase(), shown = 0;
    people.forEach(function (p) {
      var match = !q || p.getAttribute('data-q').indexOf(q) !== -1;
      p.style.display = match ? '' : 'none';
      if (match) { shown++; }
    });
    noMatch.classList.toggle('d-none', shown > 0);
    return people.filter(function (p) { return p.style.display !== 'none'; });
  }
  search.addEventListener('input', visiblePeople);
  document.getElementById('ndgPIncludeShown').addEventListener('click', function () {
    visiblePeople().forEach(function (p) { p.querySelector('.ndg-p-check').checked = true; });
    updateCount();
  });
  document.getElementById('ndgPExcludeShown').addEventListener('click', function () {
    visiblePeople().forEach(function (p) { p.querySelector('.ndg-p-check').checked = false; });
    updateCount();
  });

  // ── end date auto-fills to start + 90 days, until the admin edits it directly ──
  var startEl = document.getElementById('ndgPStart'), endEl = document.getElementById('ndgPEnd'), endTouched = false;
  function addDays(dateStr, days) {
    var d = new Date(dateStr + 'T00:00:00');
    d.setDate(d.getDate() + days);
    // Local date parts - toISOString() converts to UTC, which in IST is the previous day.
    var pad = function (n) { return (n < 10 ? '0' : '') + n; };
    return d.getFullYear() + '-' + pad(d.getMonth() + 1) + '-' + pad(d.getDate());
  }
  startEl.addEventListener('change', function () {
    if (startEl.value && !endTouched) { endEl.value = addDays(startEl.value, ROUND_SPAN_DAYS); }
  });
  endEl.addEventListener('input', function () { endTouched = true; });

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
        if (!j.success) {
          msg.textContent = j.message || (j.errors ? Object.values(j.errors).join(' ') : 'Could not create the program.');
          if (typeof window.showErrorSwal === 'function') { window.showErrorSwal(msg.textContent); }
          return;
        }
        if (typeof window.showSuccessSwal === 'function') {
          window.showSuccessSwal(j.message).then(function () { location.href = BACK; });
        } else {
          location.href = BACK;
        }
      }).catch(function () { btn.disabled = false; msg.textContent = 'Request failed. Try again.'; });
  });
})();
</script>
@endsection
