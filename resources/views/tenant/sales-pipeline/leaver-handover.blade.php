@extends('layouts/layoutMaster')

@section('title', 'Leaver Handover')

@section('vendor-style')
  @vite([
    'resources/assets/vendor/libs/animate-css/animate.scss',
    'resources/assets/vendor/scss/pages/hitech-portal.scss'
  ])
@endsection

@section('content')
<div class="layout-full-width animate__animated animate__fadeIn">

  <div class="d-flex flex-wrap align-items-center justify-content-between gap-3 mb-6 mx-4">
    <div>
      <h3 class="fw-extrabold mb-0 text-dark">Leaver Handover</h3>
      <p class="text-muted mb-0 mt-1">
        Hand a leaver's customers to a replacement from a month you choose. Earlier months stay credited to the leaver.
      </p>
    </div>
    <a href="{{ route('sales-visits.pipeline.index') }}" class="btn btn-hitech shadow-sm rounded-pill px-5 d-flex align-items-center gap-2">
      <i class="bx bx-left-arrow-alt fs-5"></i><span class="fw-bold">Sales Report</span>
    </a>
  </div>

  {{-- 1. Who left and still holds customers --}}
  <div class="px-4 mb-6">
    <div class="hitech-card-white p-0 overflow-hidden shadow-sm">
      <div class="p-4 pb-2"><h5 class="fw-bold mb-1 text-dark">1. People who have left and still hold customers</h5>
        <div class="small text-muted">Customers they hold on the Sales Report, by role. Pick one to hand over.</div></div>
      <div class="table-responsive">
        <table class="table m-0 shadow-none border-0">
          <thead class="text-dark"><tr>
            <th class="ps-4">Employee</th><th>Left</th><th class="text-center">Salesperson</th><th class="text-center">CCare</th>
            <th class="text-center">New Biz</th><th class="text-center">Extra-rep tags</th><th class="text-end pe-4"></th>
          </tr></thead>
          <tbody>
          @forelse($leavers as $l)
            <tr style="border-bottom:1px solid #f1f5f9;">
              <td class="ps-4 fw-medium">{{ $l['name'] }} <span class="badge bg-label-secondary rounded-pill ms-1">{{ $l['status'] }}</span></td>
              <td>{{ $l['left'] ? \Carbon\Carbon::parse($l['left'])->format('d M Y') : '—' }}</td>
              <td class="text-center">{{ $l['salesperson'] ?: '—' }}</td>
              <td class="text-center">{{ $l['ccare'] ?: '—' }}</td>
              <td class="text-center">{{ $l['new_biz'] ?: '—' }}</td>
              <td class="text-center">{{ $l['tags'] ?: '—' }}</td>
              <td class="text-end pe-4">
                <button type="button" class="btn btn-hitech rounded-pill px-4 py-1 js-pick" data-id="{{ $l['id'] }}" data-name="{{ $l['name'] }}"
                        data-sp="{{ $l['salesperson'] }}" data-cc="{{ $l['ccare'] }}" data-nb="{{ $l['new_biz'] }}">Hand over</button>
              </td>
            </tr>
          @empty
            <tr><td colspan="7" class="text-center py-5 text-muted"><i class="bx bx-check-circle mb-2" style="font-size:2.5rem;"></i><p class="mb-0">No leaver holds any customer.</p></td></tr>
          @endforelse
          </tbody>
        </table>
      </div>
    </div>
  </div>

  {{-- 2. Choose replacements and the month --}}
  <div class="px-4 mb-6 d-none" id="hoForm">
    <div class="hitech-card-white p-4 shadow-sm">
      <h5 class="fw-bold mb-1 text-dark">2. Hand over <span id="hoName"></span></h5>
      <div class="small text-muted mb-4">Choose who takes over each role. Leave a role empty only if the leaver holds nothing in it.</div>
      <div class="row g-4">
        @foreach([['salesperson','Salesperson','sp'],['ccare','CCare','cc'],['new_biz','New Biz','nb']] as [$key,$label,$short])
          <div class="col-lg-4 col-md-6">
            <label class="form-label fw-semibold">{{ $label }} replacement <span class="text-muted small" id="hoCount_{{ $short }}"></span></label>
            <select class="form-select js-repl" id="ho_{{ $key }}" data-key="{{ $key }}">
              <option value="">— none —</option>
              @foreach($people->groupBy('dept') as $dept => $list)
                <optgroup label="{{ $dept }}">@foreach($list as $p)<option value="{{ $p['id'] }}">{{ $p['name'] }}</option>@endforeach</optgroup>
              @endforeach
            </select>
          </div>
        @endforeach
        <div class="col-lg-4 col-md-6">
          <label class="form-label fw-semibold">Client Master billing <span class="text-muted small">(optional)</span></label>
          <select class="form-select js-repl" id="ho_billing" data-key="billing"><option value="">— none —</option>
            @foreach($people->groupBy('dept') as $dept => $list)<optgroup label="{{ $dept }}">@foreach($list as $p)<option value="{{ $p['id'] }}">{{ $p['name'] }}</option>@endforeach</optgroup>@endforeach
          </select>
        </div>
        <div class="col-lg-4 col-md-6">
          <label class="form-label fw-semibold">Client Master finance <span class="text-muted small">(optional)</span></label>
          <select class="form-select js-repl" id="ho_finance" data-key="finance"><option value="">— none —</option>
            @foreach($people->groupBy('dept') as $dept => $list)<optgroup label="{{ $dept }}">@foreach($list as $p)<option value="{{ $p['id'] }}">{{ $p['name'] }}</option>@endforeach</optgroup>@endforeach
          </select>
        </div>
        <div class="col-lg-4 col-md-6">
          <label class="form-label fw-semibold">Effective month</label>
          <select class="form-select" id="ho_month">
            @foreach($months as $m)<option value="{{ $m['key'] }}">{{ $m['label'] }}</option>@endforeach
          </select>
          <div class="small text-muted mt-1">The replacement is credited from the 1st of this month. Months before it stay with the leaver.</div>
        </div>
      </div>
      <div class="mt-4 d-flex gap-3">
        <button type="button" class="btn btn-hitech rounded-pill px-5" id="hoPreview"><i class="bx bx-search-alt me-1"></i>Preview (changes nothing)</button>
      </div>
      <div id="hoResult" class="mt-4"></div>
    </div>
  </div>

  {{-- 3. History --}}
  <div class="px-4 mb-6">
    <div class="hitech-card-white p-0 overflow-hidden shadow-sm">
      <div class="p-4 pb-2"><h5 class="fw-bold mb-1 text-dark">Recent handovers</h5>
        <div class="small text-muted">Each can be undone while nothing has changed on those customers since.</div></div>
      <div class="table-responsive">
        <table class="table m-0 shadow-none border-0">
          <thead class="text-dark"><tr><th class="ps-4">#</th><th>Leaver</th><th>From</th><th>Customers</th><th>Done by</th><th>When</th><th>Status</th><th class="text-end pe-4"></th></tr></thead>
          <tbody>
          @forelse($batches as $b)
            <tr style="border-bottom:1px solid #f1f5f9;">
              <td class="ps-4">{{ $b->id }}</td>
              <td class="fw-medium">{{ $b->leaver_name }}</td>
              <td>{{ \Carbon\Carbon::createFromFormat('Y-m', $b->effective_month)->format('M Y') }}</td>
              <td>{{ $b->summary['customers'] ?? '—' }}</td>
              <td>{{ $b->by }}</td>
              <td>{{ \Carbon\Carbon::parse($b->created_at)->format('d M Y H:i') }}</td>
              <td>@if($b->status === 'applied')<span class="badge bg-teal-light text-teal rounded-pill px-3">Applied</span>@else<span class="badge bg-label-secondary rounded-pill px-3">Undone</span>@endif</td>
              <td class="text-end pe-4">@if($b->status === 'applied')<button type="button" class="btn btn-sm btn-hitech-secondary rounded-pill px-3 js-revert" data-id="{{ $b->id }}">Undo</button>@endif</td>
            </tr>
          @empty
            <tr><td colspan="8" class="text-center py-4 text-muted">Nothing has been handed over yet.</td></tr>
          @endforelse
          </tbody>
        </table>
      </div>
    </div>
  </div>
</div>

<script>
(function () {
  var csrf = @json(csrf_token());
  var urls = { preview: @json(route('sales-visits.leaver-handover.preview')), apply: @json(route('sales-visits.leaver-handover.apply')), revert: @json(url('sales-visits/leaver-handover')) };
  var leaverId = null, lastPreview = null;
  var $ = function (id) { return document.getElementById(id); };
  var esc = function (s) { var d = document.createElement('div'); d.textContent = s == null ? '' : String(s); return d.innerHTML; };

  function post(url, body) {
    return fetch(url, { method: 'POST', headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': csrf, 'X-Requested-With': 'XMLHttpRequest' }, body: JSON.stringify(body) })
      .then(function (r) { return r.json().then(function (j) { return { ok: r.ok, status: r.status, body: j }; }); });
  }
  function payload() {
    var p = { leaver_id: leaverId, month: $('ho_month').value };
    document.querySelectorAll('.js-repl').forEach(function (s) { p[s.dataset.key] = s.value || null; });
    return p;
  }

  document.querySelectorAll('.js-pick').forEach(function (b) {
    b.addEventListener('click', function () {
      leaverId = b.dataset.id;
      $('hoName').textContent = b.dataset.name;
      $('hoCount_sp').textContent = b.dataset.sp > 0 ? '(' + b.dataset.sp + ' customers)' : '';
      $('hoCount_cc').textContent = b.dataset.cc > 0 ? '(' + b.dataset.cc + ' customers)' : '';
      $('hoCount_nb').textContent = b.dataset.nb > 0 ? '(' + b.dataset.nb + ' customers)' : '';
      document.querySelectorAll('.js-repl').forEach(function (s) { s.value = ''; });
      $('hoResult').innerHTML = ''; lastPreview = null;
      $('hoForm').classList.remove('d-none');
      $('hoForm').scrollIntoView({ behavior: 'smooth', block: 'start' });
    });
  });

  $('hoPreview').addEventListener('click', function () {
    $('hoResult').innerHTML = '<div class="text-muted">Checking…</div>';
    post(urls.preview, payload()).then(function (res) {
      if (!res.ok) { $('hoResult').innerHTML = '<div class="alert alert-danger mb-0">' + esc((res.body && res.body.message) || 'Could not preview.') + '</div>'; return; }
      var d = res.body, s = d.summary; lastPreview = d;
      var h = '';
      d.errors.forEach(function (e) { h += '<div class="alert alert-danger py-2 mb-2"><i class="bx bx-error-circle me-1"></i>' + esc(e) + '</div>'; });
      d.warnings.forEach(function (w) { h += '<div class="alert alert-warning py-2 mb-2"><i class="bx bx-info-circle me-1"></i>' + esc(w) + '</div>'; });
      h += '<div class="p-3 rounded-3 mb-3" style="background:#f8fafc;border:1px solid #e2e8f0;">'
        + '<div><strong>' + s.customers + '</strong> customer(s), <strong>' + s.role_moves + '</strong> role change(s); <strong>' + s.tags_ending + '</strong> extra-rep tag(s) end; <strong>' + s.client_master + '</strong> Client Master record(s); <strong>' + s.ccare_map_added + '</strong> CCare-map row(s) added.</div>'
        + '<div class="mt-1">Stays with the leaver (months before ' + esc(d.month) + '): <strong>' + s.sale_stays_L + ' L</strong>. Goes to the replacement (' + esc(d.month) + ' on): <strong>' + s.sale_moves_L + ' L</strong>.</div></div>';
      if (d.sample.length) {
        h += '<div class="table-responsive mb-3" style="max-height:260px;overflow:auto;"><table class="table table-sm"><thead><tr><th>#</th><th>Customer</th><th>Role</th><th>Goes to</th></tr></thead><tbody>';
        d.sample.forEach(function (r) { h += '<tr><td>' + r.id + '</td><td>' + esc(r.party) + '</td><td>' + esc(r.role) + '</td><td>' + esc(r.to) + '</td></tr>'; });
        h += '</tbody></table></div>' + (d.more ? '<div class="small text-muted mb-3">…and ' + d.more + ' more.</div>' : '');
      }
      if (!d.errors.length) {
        h += '<div class="d-flex flex-wrap align-items-end gap-3"><div><label class="form-label fw-semibold mb-1">Type HANDOVER to confirm</label>'
          + '<input type="text" class="form-control" id="hoConfirm" autocomplete="off" placeholder="HANDOVER"></div>'
          + '<button type="button" class="btn btn-hitech rounded-pill px-5" id="hoApply"><i class="bx bx-transfer-alt me-1"></i>Apply handover</button></div>';
      }
      $('hoResult').innerHTML = h;
      var a = $('hoApply');
      if (a) a.addEventListener('click', function () {
        var p = payload(); p.fingerprint = lastPreview.fingerprint; p.confirm = $('hoConfirm').value.trim();
        a.disabled = true;
        post(urls.apply, p).then(function (r) {
          if (r.ok) { location.reload(); return; }
          a.disabled = false;
          $('hoResult').insertAdjacentHTML('afterbegin', '<div class="alert alert-danger py-2 mb-2">' + esc((r.body && (r.body.message || (r.body.errors && JSON.stringify(r.body.errors)))) || 'Failed.') + '</div>');
        });
      });
    });
  });

  document.querySelectorAll('.js-revert').forEach(function (b) {
    b.addEventListener('click', function () {
      if (!confirm('Undo handover #' + b.dataset.id + '? Everything it moved goes back.')) return;
      b.disabled = true;
      post(urls.revert + '/' + b.dataset.id + '/revert', {}).then(function (r) {
        if (r.ok) { location.reload(); return; }
        b.disabled = false; alert((r.body && r.body.message) || 'Could not undo.');
      });
    });
  });
})();
</script>
@endsection
