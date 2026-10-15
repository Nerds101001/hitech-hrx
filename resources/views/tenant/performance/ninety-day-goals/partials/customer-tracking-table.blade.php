@php
  // Amounts are stored in lakh (the including page usually defines this too).
  $fmtL = $fmtL ?? fn ($v) => rtrim(rtrim(number_format((float) $v, 2), '0'), '.') ?: '0';
  // Every week's comments for the rows on this page, in one query (a page can be "All").
  \Illuminate\Database\Eloquent\Collection::make($paged->getCollection()->pluck('goal')->filter()->values())->load('checkins.notes.author');
  $who = fn ($u) => $u ? trim($u->first_name . ' ' . $u->last_name) : 'Someone';
@endphp
{{-- Filters + the full weekly-status grid. @include'd from both Overview and Customers — same
     $paged/$filters/$weeks the parent view already built, so it always matches the tab it's
     rendered into. --}}
<div class="hitech-card mb-4">
  <div class="card-body p-3">
    <form method="GET" class="row g-2 align-items-end">
      <input type="hidden" name="w" value="{{ $selectedWeek }}">
      <div class="col-md-3"><input type="text" name="customer" value="{{ $filters['customer'] }}" class="form-control" placeholder="Search customer, product…"></div>
      <div class="col-md-2">
        <select name="status" class="form-select form-select-hitech" onchange="this.form.submit()">
          <option value="">All Status</option>
          @foreach($statuses as $s)<option value="{{ $s }}" {{ $filters['status'] === $s ? 'selected' : '' }}>{{ $s }}</option>@endforeach
        </select>
      </div>
      <div class="col-md-2">
        <select name="brand" class="form-select form-select-hitech" onchange="this.form.submit()">
          <option value="">All Products</option>
          @foreach($brands as $b)<option value="{{ $b }}" {{ $filters['brand'] === $b ? 'selected' : '' }}>{{ $b }}</option>@endforeach
        </select>
      </div>
      <div class="col-md-2">
        <select name="user_id" class="form-select form-select-hitech" onchange="this.form.submit()">
          <option value="">All Salespersons</option>
          @foreach($people as $p)<option value="{{ $p->id }}" {{ (int) $filters['user_id'] === $p->id ? 'selected' : '' }}>{{ $p->first_name }} {{ $p->last_name }}</option>@endforeach
        </select>
      </div>
      <div class="col-md-3 d-flex gap-2">
        <button type="submit" class="btn-hitech rounded-pill px-4 flex-grow-1">Apply</button>
        <a href="{{ route('performance.ninety-day-goals.program', $program->id) }}" class="btn btn-hitech-cancel rounded-pill px-3">Clear</a>
        <a href="{{ route('performance.ninety-day-goals.admin-view.export', array_merge($filters, ['program_id' => $program->id])) }}" class="btn btn-hitech-cancel rounded-pill px-3"><i class="bx bx-download"></i></a>
      </div>
    </form>
  </div>
</div>

<div class="hitech-card mb-6">
  <div class="card-body p-4 border-bottom d-flex flex-wrap align-items-center justify-content-between gap-2">
    <div class="d-flex flex-wrap align-items-center gap-3">
      <div class="fw-bold text-dark">Customer Tracking &amp; Weekly Updates ({{ $rows->count() }} Customers)</div>
      @include('tenant.performance.ninety-day-goals.partials.per-page', ['ppDefault' => 25, 'ppId' => 'Ct' . ($ctId ?? '')])
    </div>
    <div class="d-flex flex-wrap gap-3 small text-muted">
      @foreach($G::STATUS_COLORS as $st => [$bg, $fg])
        <span class="d-inline-flex align-items-center gap-1"><span style="width:10px;height:10px;border-radius:3px;display:inline-block;border:1px solid rgba(0,0,0,.08);background:{{ $bg }}"></span>{{ $st }}</span>
      @endforeach
    </div>
  </div>
  {{-- Row-height and alignment fixes: the # / Customer sticky columns get fixed
       widths so the header lines up with the body, and the free-text columns are
       held to one line (full text stays in the tooltip / History) so a long name
       or comment no longer blows a row up to two or three times the height. --}}
  @once
  <style>
    /* One fixed row height everywhere - every data cell on one line, so a long
       comment can no longer make a row 2-3x tall or stretch the week squares. */
    .ndg-mx { border-spacing: 2px; table-layout: auto; }
    .ndg-mx th, .ndg-mx td { vertical-align: middle; white-space: nowrap; }
    .ndg-mx tbody td { height: 30px; padding-top: 2px; padding-bottom: 2px; }
    .ndg-mx thead th { padding: 6px 4px; }
    .ndg-mx-l[data-col="no"] { width: 30px; min-width: 30px; }
    .ndg-mx-l[data-col="cust"] { left: 36px !important; width: 170px; min-width: 170px; max-width: 170px; }
    .ndg-mx .ndg-ell { max-width: 130px; overflow: hidden; text-overflow: ellipsis; }
    .ndg-mx td.ndg-ell, .ndg-mx th.ndg-ell { text-align: left; }
    .ndg-mx-l[data-col="cust"] { overflow: hidden; text-overflow: ellipsis; }
    /* Compact week squares so more of the grid fits before scrolling. */
    .ndg-mx-c { width: 16px !important; height: 16px !important; min-width: 16px !important; }
    .ndg-mx td.ndg-lastc { max-width: 210px; overflow: hidden; text-overflow: ellipsis; text-align: left; }
  </style>
  @endonce
  <div class="card-body p-0 ndg-mx-scroll">
    <table class="ndg-mx" style="font-size:.74rem">
      <thead><tr>
        <th class="ndg-mx-l" data-col="no" style="left:0">#</th><th class="ndg-mx-l ndg-ell" data-col="cust" style="left:40px">Customer</th><th class="ndg-ell">Product</th><th class="ndg-ell">Salesperson</th><th>Target ₹L</th><th>Expected</th>
        @for($w = 0; $w < $weeks; $w++)<th>W{{ $w + 1 }}</th>@endfor
        <th style="text-align:right">Actual ₹L</th><th>Ach.%</th><th style="text-align:left">Status</th><th style="text-align:left">Last Comment</th>
        @if($canManage ?? false)<th>Actions</th>@endif
      </tr></thead>
      <tbody>
      @forelse($paged as $i => $r)
        @php
          $cks = $r->goal->checkins->keyBy('week_no');
          // Every week that has something to read: a status, the salesperson's comment, or a
          // comment from a lead/admin. Newest week first for "Last Comment", oldest first in History.
          $hist = $r->goal->checkins->sortBy('week_no')
              ->filter(fn ($c) => $c->status || $c->comment || $c->notes->isNotEmpty())
              ->map(fn ($c) => [
                  'week' => (int) $c->week_no,
                  'due' => $c->due_on ? $c->due_on->format('d M') : '',
                  'status' => $c->status,
                  'comment' => $c->comment,
                  'next' => $c->next_action ? $c->next_action . ($c->next_follow_up_date ? ' (' . $c->next_follow_up_date->format('d M') . ')' : '') : null,
                  'late' => (bool) $c->is_late,
                  'notes' => $c->notes->map(fn ($n) => ['by' => $who($n->author), 'body' => $n->body, 'is_admin' => \App\Services\NinetyDayGoalService::managesEveryone($n->author)])->values()->all(),
              ])->values();
          $last = null;
          foreach ($hist->reverse() as $h) {
              if ($h['notes']) { $nn = end($h['notes']); $last = ['text' => $nn['body'], 'by' => $nn['by'], 'week' => $h['week']]; break; }
              if ($h['comment']) { $last = ['text' => $h['comment'], 'by' => $name($r->user), 'week' => $h['week']]; break; }
          }
          $nComments = $hist->sum(fn ($h) => ($h['comment'] ? 1 : 0) + count($h['notes']));
        @endphp
        <tr>
          <td class="ndg-mx-l" data-col="no" style="left:0">{{ ($paged->currentPage() - 1) * $paged->perPage() + $i + 1 }}</td>
          <td class="ndg-mx-l ndg-ell" data-col="cust" style="left:40px;font-weight:600" title="{{ $r->goal->client_name }}">{{ $r->goal->client_name }}</td>
          <td class="ndg-ell" title="{{ $r->goal->product }}">{{ $r->goal->product }}</td>
          <td class="small text-muted ndg-ell" title="{{ $name($r->user) }}">{{ $name($r->user) }}</td>
          <td>{{ $fmtL($r->goal->monthly_potential) }}</td>
          <td class="small text-muted">{{ $r->goal->expected_order_date ? $r->goal->expected_order_date->format('d M Y') : '—' }}</td>
          @for($w = 0; $w < $weeks; $w++)
            @php
              $ck = $cks->get($w);
              $st = optional($ck)->status;
              [$bg] = $st ? ($G::STATUS_COLORS[$st] ?? ['#94a3b8']) : [null];
            @endphp
            @php
              $tip = 'W' . $w . ($ck && $ck->due_on ? ' · ' . $ck->due_on->format('d M') : '') . ': ' . ($st ?: 'not submitted')
                  . ($ck && $ck->comment ? "\n" . $ck->comment : '')
                  . ($ck && $ck->next_action ? "\nNext: " . $ck->next_action : '');
              if ($ck) { foreach ($ck->notes as $n) { $tip .= "\n" . $who($n->author) . ': ' . $n->body; } }
            @endphp
            <td class="ndg-mx-c" style="{{ $bg ? 'background:' . $bg : '' }}" title="{{ $tip }}"></td>
          @endfor
          <td class="fw-semibold" style="text-align:right">{{ $fmtL($r->achieved) }}</td>
          <td style="text-align:center;color:{{ $pctColor($r->pct) }}">{{ $pctText($r->pct) }}</td>
          <td>@if($r->status)<span class="ndg-chip" style="background:{{ $G::STATUS_COLORS[$r->status][0] ?? '#e2e8f0' }};color:{{ $G::STATUS_COLORS[$r->status][1] ?? '#475569' }}">{{ $r->status }}</span>@include('tenant.performance.ninety-day-goals.partials.order-check', ['r' => $r])@else<span class="ndg-none">·</span>@endif</td>
          <td class="small ndg-lastc">
            @if($last)
              <span class="text-dark" title="{{ $last['by'] }} · W{{ $last['week'] + 1 }}: {{ $last['text'] }}">{{ \Illuminate\Support\Str::limit($last['text'], 38) }}</span>
            @else
              <span class="text-muted">No comments yet</span>
            @endif
            @if($hist->isNotEmpty())
              <span role="button" tabindex="0" class="ndg-hist-btn text-primary ms-1" style="font-size:.66rem;text-decoration:underline;cursor:pointer"
                      data-customer="{{ $r->goal->client_name }}" data-goal="{{ $r->goal->id }}" data-history="{{ json_encode($hist) }}" title="View the full history{{ $nComments ? ' (' . $nComments . ' comment' . ($nComments === 1 ? '' : 's') . ')' : '' }}">History{{ $nComments ? ' (' . $nComments . ')' : '' }}</span>
            @endif
          </td>
          @if($canManage ?? false)
            <td class="text-nowrap">
              <div class="dropdown">
                <button class="btn btn-sm btn-icon" data-bs-toggle="dropdown"><i class="bx bx-dots-vertical-rounded"></i></button>
                <ul class="dropdown-menu dropdown-menu-end">
                  <li><button type="button" class="dropdown-item ndg-ct-edit" data-id="{{ $r->goal->id }}" data-product="{{ e($r->goal->product) }}" data-brand="{{ e($r->goal->brand) }}" data-goal="{{ e($r->goal->goal ?: $r->goal->goal_type) }}" data-potential="{{ $r->goal->monthly_potential }}" data-expected="{{ optional($r->goal->expected_order_date)->format('Y-m-d') }}" data-title="{{ e($r->goal->client_name) }}">Edit</button></li>
                  <li><button type="button" class="dropdown-item text-danger ndg-ct-remove" data-id="{{ $r->goal->id }}" data-title="{{ e($r->goal->client_name) }}">Remove customer</button></li>
                </ul>
              </div>
            </td>
          @endif
        </tr>
      @empty
        <tr><td colspan="{{ 9 + $weeks + ($canManage ?? false ? 1 : 0) }}" class="text-center text-muted p-5">
          No customers have been added to this program yet — {{ $participants }} {{ \Illuminate\Support\Str::plural('participant', $participants) }} enrolled, none have added a target customer.
        </td></tr>
      @endforelse
      </tbody>
    </table>
  </div>
  <div class="card-body pt-3">{{ $paged->links() }}</div>
</div>

@once
@if($canManage ?? false)
{{-- Edit a customer's target / Remove it outright — admin/HR only, same endpoints the
     per-person round page uses (PUT/DELETE /ninety-day-goals/{id}). --}}
<div class="modal fade" id="ndgCtEditModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">Edit <span class="ndg-ct-edit-title"></span></h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">
        <input type="hidden" name="goal_id">
        <div class="mb-3"><label class="form-label">Product</label><input type="text" name="product" class="form-control" maxlength="255"></div>
        <div class="mb-3"><label class="form-label">Brand</label><input type="text" name="brand" class="form-control" maxlength="100"></div>
        <div class="mb-3"><label class="form-label">Goal</label><input type="text" name="goal" class="form-control" maxlength="255"></div>
        <div class="mb-3"><label class="form-label">Target (₹ Lakh)</label><input type="number" step="0.01" min="0" name="monthly_potential" class="form-control"></div>
        <div class="mb-3"><label class="form-label">Expected Order Date</label><input type="date" name="expected_order_date" class="form-control"></div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-hitech-cancel rounded-pill px-4" data-bs-dismiss="modal">Cancel</button>
        <button type="button" class="btn-hitech rounded-pill px-4" id="ndgCtSaveGoal">Save</button>
      </div>
    </div>
  </div>
</div>
<script>
(function () {
  var CSRF = document.querySelector('meta[name="csrf-token"]').getAttribute('content');
  var BASE = '{{ url('performance/ninety-day-goals') }}';
  function post(url, method, body) {
    return fetch(url, { method: method, headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': CSRF }, body: body ? JSON.stringify(body) : undefined })
      .then(function (r) { return r.json(); });
  }
  document.addEventListener('click', function (e) {
    var edit = e.target.closest ? e.target.closest('.ndg-ct-edit') : null;
    if (edit) {
      var modalEl = document.getElementById('ndgCtEditModal');
      modalEl.querySelector('[name=goal_id]').value = edit.dataset.id;
      modalEl.querySelector('[name=product]').value = edit.dataset.product || '';
      modalEl.querySelector('[name=brand]').value = edit.dataset.brand || '';
      modalEl.querySelector('[name=goal]').value = edit.dataset.goal || '';
      modalEl.querySelector('[name=monthly_potential]').value = edit.dataset.potential || '';
      modalEl.querySelector('[name=expected_order_date]').value = edit.dataset.expected || '';
      modalEl.querySelector('.ndg-ct-edit-title').textContent = edit.dataset.title || '';
      new bootstrap.Modal(modalEl).show();
      return;
    }
    var rm = e.target.closest ? e.target.closest('.ndg-ct-remove') : null;
    if (rm) {
      if (!confirm('Remove "' + (rm.dataset.title || 'this customer') + '" from the program? This also removes its weekly history.')) { return; }
      post(BASE + '/' + rm.dataset.id, 'DELETE').then(function (j) {
        if (!j.success) { alert(j.message || 'Could not remove it.'); return; }
        location.reload();
      }).catch(function () { alert('Request failed. Try again.'); });
    }
  });
  var saveBtn = document.getElementById('ndgCtSaveGoal');
  if (saveBtn) {
    saveBtn.addEventListener('click', function () {
      var modalEl = document.getElementById('ndgCtEditModal');
      var id = modalEl.querySelector('[name=goal_id]').value;
      post(BASE + '/' + id, 'PUT', {
        product: modalEl.querySelector('[name=product]').value,
        brand: modalEl.querySelector('[name=brand]').value,
        goal: modalEl.querySelector('[name=goal]').value,
        monthly_potential: parseFloat(modalEl.querySelector('[name=monthly_potential]').value || '0'),
        expected_order_date: modalEl.querySelector('[name=expected_order_date]').value || null,
      }).then(function (j) {
        if (j.success) { location.reload(); return; }
        alert(j.message || 'Could not save.');
      });
    });
  }
})();
</script>
@endif
{{-- One customer's whole timeline: every week's status, the salesperson's comment and next
     step, and comments from their lead or admin. --}}
<div class="modal fade" id="ndgHistModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="ndgHistTitle">Weekly history</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body" id="ndgHistBody"></div>
      <div class="modal-footer"><button type="button" class="btn btn-hitech-cancel rounded-pill px-4" data-bs-dismiss="modal">Close</button></div>
    </div>
  </div>
</div>
<script>
(function () {
  var CAN_MANAGE = @json($canManage ?? false);
  var CSRF_EL = document.querySelector('meta[name="csrf-token"]');
  var NOTES_URL = '{{ url('performance/ninety-day-goals/notes') }}';
  var esc = function (s) { return String(s == null ? '' : s).replace(/[&<>"']/g, function (c) { return {'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]; }); };

  function renderHist(btn) {
    var weeks = [];
    try { weeks = JSON.parse(btn.getAttribute('data-history') || '[]'); } catch (err) { weeks = []; }
    document.getElementById('ndgHistTitle').textContent = 'Weekly history · ' + btn.getAttribute('data-customer');
    var html = weeks.length ? '' : '<div class="text-muted">Nothing submitted yet.</div>';
    weeks.forEach(function (w) {
      html += '<div class="border-bottom py-2">'
        + '<div class="d-flex align-items-center gap-2 mb-1"><b>W' + w.week + '</b><span class="text-muted small">' + esc(w.due) + '</span>'
        + (w.status ? '<span class="badge bg-label-secondary">' + esc(w.status) + '</span>' : '<span class="text-muted small">not submitted</span>')
        + (w.late ? '<span class="badge bg-label-warning">late</span>' : '') + '</div>'
        + (w.comment ? '<div class="small">' + esc(w.comment) + '</div>' : '')
        + (w.next ? '<div class="small text-muted"><b>Next:</b> ' + esc(w.next) + '</div>' : '')
        + (w.notes || []).map(function (n) {
            return '<div class="small mt-1 ps-2 border-start">' + (n.is_admin ? '<span class="badge badge-hitech me-1" style="background:#fef3c7;color:#92400e;font-size:.6rem">Admin</span>' : '')
              + '<b>' + esc(n.by) + ':</b> ' + esc(n.body) + '</div>';
          }).join('')
        + (CAN_MANAGE ? '<button type="button" class="btn btn-link btn-sm p-0 mt-1 ndg-hist-reply" data-goal="' + btn.getAttribute('data-goal') + '" data-week="' + w.week + '"><i class="bx bx-comment-add"></i> Reply</button>' : '')
        + '</div>';
    });
    document.getElementById('ndgHistBody').innerHTML = html;
    if (window.bootstrap && window.bootstrap.Modal) { window.bootstrap.Modal.getOrCreateInstance(document.getElementById('ndgHistModal')).show(); }
  }

  document.addEventListener('click', function (e) {
    var btn = e.target.closest ? e.target.closest('.ndg-hist-btn') : null;
    if (btn) { renderHist(btn); return; }

    var reply = e.target.closest ? e.target.closest('.ndg-hist-reply') : null;
    if (reply) {
      var body = window.prompt('Reply for W' + reply.getAttribute('data-week') + ':');
      if (body === null || !body.trim()) { return; }
      reply.disabled = true;
      fetch(NOTES_URL, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': CSRF_EL.getAttribute('content') },
        body: JSON.stringify({ goal_id: parseInt(reply.getAttribute('data-goal'), 10), week_no: parseInt(reply.getAttribute('data-week'), 10), body: body.trim() }),
      }).then(function (r) { return r.json(); }).then(function (j) {
        if (!j.success) { reply.disabled = false; alert(j.message || 'Could not save the reply.'); return; }
        location.reload();
      }).catch(function () { reply.disabled = false; alert('Request failed. Try again.'); });
    }
  });
})();
</script>
@endonce
