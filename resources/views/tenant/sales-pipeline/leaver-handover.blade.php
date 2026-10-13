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
        Hand a leaver's customers to a replacement, or transfer them between people still working, from a month you choose. Earlier months stay credited to the previous person.
      </p>
    </div>
    <a href="{{ route('sales-visits.pipeline.index') }}" class="btn btn-hitech shadow-sm rounded-pill px-5 d-flex align-items-center gap-2">
      <i class="bx bx-left-arrow-alt fs-5"></i><span class="fw-bold">Sales Report</span>
    </a>
  </div>

  {{-- 1. Who holds customers: people who have left (handover) and people still working (transfer) --}}
  @foreach([
    ['People who have left and still hold customers', 'Handover: everything they hold needs a new owner.', $leavers, 'Hand over', 'handover'],
    ['People still working', 'Transfer: move some or all of a working employee\'s customers (for example after a role change). A role you leave empty stays with them.', $workers, 'Transfer', 'transfer'],
  ] as [$title, $sub, $list, $btn, $kind])
  <div class="px-4 mb-6">
    <div class="hitech-card-white p-0 overflow-hidden shadow-sm">
      <div class="p-4 pb-2 d-flex flex-wrap align-items-end justify-content-between gap-3">
        <div><h5 class="fw-bold mb-1 text-dark">{{ $loop->iteration }}. {{ $title }}</h5>
          <div class="small text-muted">{{ $sub }}</div></div>
        <div class="d-flex flex-wrap gap-2">
          <input type="search" class="form-control js-lf-text" data-table="tbl_{{ $kind }}" style="min-width:220px;" placeholder="Search by name…">
          <select class="form-select js-lf-role" data-table="tbl_{{ $kind }}" style="min-width:170px;">
            <option value="">Any role held</option>
            <option value="sp">Holds as Salesperson</option>
            <option value="cc">Holds as CCare</option>
            <option value="nb">Holds as New Biz</option>
            <option value="tags">Has extra-rep tags</option>
          </select>
        </div>
      </div>
      <div class="table-responsive" @if($kind === 'transfer') style="max-height:420px;overflow:auto;" @endif>
        <table class="table m-0 shadow-none border-0" id="tbl_{{ $kind }}">
          <thead class="text-dark"><tr>
            <th class="ps-4">Employee</th>@if($kind === 'handover')<th>Left</th>@endif<th class="text-center">Salesperson</th><th class="text-center">CCare</th>
            <th class="text-center">New Biz</th><th class="text-center">Extra-rep tags</th><th class="text-end pe-4"></th>
          </tr></thead>
          <tbody>
          @forelse($list as $l)
            <tr style="border-bottom:1px solid #f1f5f9;" data-text="{{ strtolower($l['name']) }}" data-sp="{{ $l['salesperson'] }}" data-cc="{{ $l['ccare'] }}" data-nb="{{ $l['new_biz'] }}" data-tags="{{ $l['tags'] }}">
              <td class="ps-4 fw-medium">{{ $l['name'] }} @if($kind === 'handover')<span class="badge bg-label-secondary rounded-pill ms-1">{{ $l['status'] }}</span>@endif</td>
              @if($kind === 'handover')<td>{{ $l['left'] ? \Carbon\Carbon::parse($l['left'])->format('d M Y') : '—' }}</td>@endif
              <td class="text-center">{{ $l['salesperson'] ?: '—' }}</td>
              <td class="text-center">{{ $l['ccare'] ?: '—' }}</td>
              <td class="text-center">{{ $l['new_biz'] ?: '—' }}</td>
              <td class="text-center">{{ $l['tags'] ?: '—' }}</td>
              <td class="text-end pe-4">
                <button type="button" class="btn btn-hitech rounded-pill px-4 py-1 js-pick" data-id="{{ $l['id'] }}" data-name="{{ $l['name'] }}" data-kind="{{ $kind }}"
                        data-sp="{{ $l['salesperson'] }}" data-cc="{{ $l['ccare'] }}" data-nb="{{ $l['new_biz'] }}">{{ $btn }}</button>
              </td>
            </tr>
          @empty
            <tr><td colspan="7" class="text-center py-5 text-muted"><i class="bx bx-check-circle mb-2" style="font-size:2.5rem;"></i><p class="mb-0">Nobody here holds any customer.</p></td></tr>
          @endforelse
          </tbody>
        </table>
      </div>
    </div>
  </div>
  @endforeach
  {{-- 2. Choose replacements and the month --}}
  <div class="px-4 mb-6 d-none" id="hoForm">
    <div class="hitech-card-white p-4 shadow-sm">
      <h5 class="fw-bold mb-1 text-dark"><span id="hoVerb">Hand over</span> <span id="hoName"></span></h5>
      <div class="small text-muted mb-4" id="hoHelp">Choose who takes over each role.</div>
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
      <div class="p-4 pb-2 d-flex flex-wrap align-items-end justify-content-between gap-3">
        <div><h5 class="fw-bold mb-1 text-dark">Recent handovers</h5>
          <div class="small text-muted">Open one for the detailed sheet of who moved to whom. Each can be undone while nothing has changed on those customers since.</div></div>
        <div class="d-flex flex-wrap gap-2">
          <input type="search" class="form-control js-lf-text" data-table="tbl_history" style="min-width:220px;" placeholder="Search by person…">
          <select class="form-select js-lf-status" data-table="tbl_history" style="min-width:150px;">
            <option value="">All</option><option value="applied">Applied</option><option value="reverted">Undone</option>
          </select>
        </div>
      </div>
      <div class="table-responsive">
        <table class="table m-0 shadow-none border-0" id="tbl_history">
          <thead class="text-dark"><tr><th class="ps-4">#</th><th>Person</th><th>From</th><th>Customers</th><th>Done by</th><th>When</th><th>Status</th><th class="text-end pe-4"></th></tr></thead>
          <tbody>
          @forelse($batches as $b)
            <tr style="border-bottom:1px solid #f1f5f9;" data-text="{{ strtolower($b->leaver_name . ' ' . $b->by) }}" data-status="{{ $b->status }}">
              <td class="ps-4">{{ $b->id }}</td>
              <td class="fw-medium"><a href="{{ route('sales-visits.leaver-handover.show', $b->id) }}" class="text-dark">{{ $b->leaver_name }}</a></td>
              <td>{{ \Carbon\Carbon::createFromFormat('Y-m', $b->effective_month)->format('M Y') }}</td>
              <td>{{ $b->summary['customers'] ?? '—' }}</td>
              <td>{{ $b->by }}</td>
              <td>{{ \Carbon\Carbon::parse($b->created_at)->format('d M Y H:i') }}</td>
              <td>@if($b->status === 'applied')<span class="badge bg-teal-light text-teal rounded-pill px-3">Applied</span>@else<span class="badge bg-label-secondary rounded-pill px-3">Undone</span>@endif</td>
              <td class="text-end pe-4 text-nowrap">
                <a href="{{ route('sales-visits.leaver-handover.show', $b->id) }}" class="btn btn-sm btn-hitech rounded-pill px-3">View sheet</a>
                @if($b->status === 'applied')<button type="button" class="btn btn-sm btn-hitech-secondary rounded-pill px-3 js-revert" data-id="{{ $b->id }}">Undo</button>@endif
              </td>
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

  // Search / filter the three lists and the history table.
  function filterTable(id) {
    var table = $(id); if (!table) return;
    var wrap = table.closest('.hitech-card-white');
    var q = (wrap.querySelector('.js-lf-text') || {}).value || '';
    var role = (wrap.querySelector('.js-lf-role') || {}).value || '';
    var status = (wrap.querySelector('.js-lf-status') || {}).value || '';
    q = q.trim().toLowerCase();
    table.querySelectorAll('tbody tr[data-text]').forEach(function (tr) {
      var ok = (!q || tr.dataset.text.indexOf(q) !== -1)
            && (!role || Number(tr.dataset[role] || 0) > 0)
            && (!status || tr.dataset.status === status);
      tr.style.display = ok ? '' : 'none';
    });
  }
  document.querySelectorAll('.js-lf-text, .js-lf-role, .js-lf-status').forEach(function (el) {
    el.addEventListener('input', function () { filterTable(el.dataset.table); });
    el.addEventListener('change', function () { filterTable(el.dataset.table); });
  });

  // Type-ahead for every replacement list: type 2-3 letters of a name (or department) and the
  // matching people appear underneath; click one to choose. The real <select> stays hidden and
  // holds the value, so nothing else changes.
  document.querySelectorAll('.js-repl').forEach(function (sel) {
    var items = [];
    sel.querySelectorAll('optgroup').forEach(function (g) {
      g.querySelectorAll('option').forEach(function (o) { items.push({ id: o.value, name: o.textContent.trim(), dept: g.label }); });
    });
    sel.style.display = 'none';

    var wrap = document.createElement('div'); wrap.style.position = 'relative';
    var box = document.createElement('input');
    box.type = 'text'; box.className = 'form-control'; box.placeholder = 'Type 2–3 letters to search…'; box.autocomplete = 'off';
    var list = document.createElement('div');
    list.className = 'list-group shadow'; list.style.cssText = 'position:absolute;z-index:1060;left:0;right:0;top:100%;margin-top:2px;max-height:260px;overflow:auto;display:none;background:#fff;border:1px solid #cbd5e1;border-radius:10px;box-shadow:0 10px 25px rgba(15,23,42,.18);';
    sel.parentNode.insertBefore(wrap, sel); wrap.appendChild(box); wrap.appendChild(list);

    function choose(it) {
      sel.value = it.id; box.value = it.name; box.classList.add('is-valid'); list.style.display = 'none'; wrap.style.zIndex = '';
      sel.dispatchEvent(new Event('change'));
    }
    function render() {
      var q = box.value.trim().toLowerCase();
      list.innerHTML = '';
      var hits = items.filter(function (i) { return !q || i.name.toLowerCase().indexOf(q) !== -1 || i.dept.toLowerCase().indexOf(q) !== -1; });
      hits.slice(0, 15).forEach(function (it) {
        var b = document.createElement('button');
        b.type = 'button'; b.className = 'list-group-item list-group-item-action py-2'; b.style.cssText = 'background:#fff;color:#1e293b;border:0;border-bottom:1px solid #f1f5f9;text-align:left;';
        var n = document.createElement('span'); n.textContent = it.name;
        var d = document.createElement('small'); d.className = 'text-muted ms-2'; d.textContent = it.dept;
        b.appendChild(n); b.appendChild(d);
        b.addEventListener('mousedown', function (e) { e.preventDefault(); choose(it); });   // before the box loses focus
        list.appendChild(b);
      });
      if (!hits.length) { var none = document.createElement('div'); none.className = 'list-group-item text-muted'; none.style.background = '#fff'; none.textContent = 'No one matches "' + box.value + '"'; list.appendChild(none); }
      else if (hits.length > 15) { var more = document.createElement('div'); more.className = 'list-group-item small text-muted'; more.style.background = '#fff'; more.textContent = (hits.length - 15) + ' more… keep typing to narrow it down'; list.appendChild(more); }
      list.style.display = 'block'; wrap.style.zIndex = 1060;
    }
    box.addEventListener('input', function () { sel.value = ''; box.classList.remove('is-valid'); render(); });
    box.addEventListener('focus', render);
    box.addEventListener('blur', function () { setTimeout(function () { list.style.display = 'none'; wrap.style.zIndex = ''; }, 150); });
    sel._reset = function () { sel.value = ''; box.value = ''; box.classList.remove('is-valid'); list.style.display = 'none'; };
  });

  document.querySelectorAll('.js-pick').forEach(function (b) {
    b.addEventListener('click', function () {
      leaverId = b.dataset.id;
      $('hoName').textContent = b.dataset.name;
      var transfer = b.dataset.kind === 'transfer';
      $('hoVerb').textContent = transfer ? 'Transfer from' : 'Hand over';
      $('hoHelp').textContent = transfer
        ? 'They are still working. Choose a replacement only for the roles you want to move; a role left empty stays with them.'
        : 'They have left. Every role they hold needs a replacement, so nothing is left without an owner.';
      $('hoCount_sp').textContent = b.dataset.sp > 0 ? '(' + b.dataset.sp + ' customers)' : '';
      $('hoCount_cc').textContent = b.dataset.cc > 0 ? '(' + b.dataset.cc + ' customers)' : '';
      $('hoCount_nb').textContent = b.dataset.nb > 0 ? '(' + b.dataset.nb + ' customers)' : '';
      document.querySelectorAll('.js-repl').forEach(function (s) { s.value = ''; if (s._reset) s._reset(); });
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
          if (r.ok) { location.href = (r.body && r.body.redirect) ? r.body.redirect : location.href; return; }
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
