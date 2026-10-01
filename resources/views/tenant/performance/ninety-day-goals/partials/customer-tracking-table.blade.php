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
      @include('tenant.performance.ninety-day-goals.partials.per-page', ['ppDefault' => 10, 'ppId' => 'Ct' . ($ctId ?? '')])
    </div>
    <div class="d-flex flex-wrap gap-3 small text-muted">
      @foreach($G::STATUS_COLORS as $st => [$bg, $fg])
        <span class="d-inline-flex align-items-center gap-1"><span style="width:9px;height:9px;border-radius:50%;display:inline-block;background:{{ $fg }}"></span>{{ $st }}</span>
      @endforeach
    </div>
  </div>
  <div class="card-body p-0 ndg-mx-scroll">
    <table class="ndg-mx" style="font-size:.74rem">
      <thead><tr>
        <th class="ndg-mx-l" style="left:0">#</th><th class="ndg-mx-l" style="left:30px">Customer</th><th>Product</th><th>Salesperson</th><th>Target ₹L</th><th>Expected</th>
        @for($w = 0; $w < $weeks; $w++)<th>W{{ $w }}</th>@endfor
        <th>Actual ₹L</th><th>Ach.%</th><th>Status</th><th>Last Comment</th>
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
                  'notes' => $c->notes->map(fn ($n) => ['by' => $who($n->author), 'body' => $n->body])->values()->all(),
              ])->values();
          $last = null;
          foreach ($hist->reverse() as $h) {
              if ($h['notes']) { $nn = end($h['notes']); $last = ['text' => $nn['body'], 'by' => $nn['by'], 'week' => $h['week']]; break; }
              if ($h['comment']) { $last = ['text' => $h['comment'], 'by' => $name($r->user), 'week' => $h['week']]; break; }
          }
          $nComments = $hist->sum(fn ($h) => ($h['comment'] ? 1 : 0) + count($h['notes']));
        @endphp
        <tr>
          <td class="ndg-mx-l" style="left:0">{{ ($paged->currentPage() - 1) * $paged->perPage() + $i + 1 }}</td>
          <td class="ndg-mx-l" style="left:30px;font-weight:600">{{ $r->goal->client_name }}</td>
          <td>{{ $r->goal->product }}</td>
          <td class="small text-muted">{{ $name($r->user) }}</td>
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
          <td class="fw-semibold">{{ $fmtL($r->achieved) }}</td>
          <td style="color:{{ $pctColor($r->pct) }}">{{ $pctText($r->pct) }}</td>
          <td>@if($r->status)<span class="ndg-chip" style="background:{{ $G::STATUS_COLORS[$r->status][0] ?? '#e2e8f0' }};color:{{ $G::STATUS_COLORS[$r->status][1] ?? '#475569' }}">{{ $r->status }}</span>@else<span class="ndg-none">·</span>@endif</td>
          <td class="small" style="max-width:200px;white-space:normal">
            @if($last)
              <div class="text-dark">{{ \Illuminate\Support\Str::limit($last['text'], 70) }}</div>
              <div class="text-muted" style="font-size:.66rem">{{ $last['by'] }} · W{{ $last['week'] }}</div>
            @else
              <span class="text-muted">No comments yet</span>
            @endif
            @if($hist->isNotEmpty())
              <button type="button" class="btn btn-link btn-sm p-0 ndg-hist-btn" style="font-size:.68rem"
                      data-customer="{{ $r->goal->client_name }}" data-history="{{ json_encode($hist) }}">History{{ $nComments ? ' (' . $nComments . ' comment' . ($nComments === 1 ? '' : 's') . ')' : '' }}</button>
            @endif
          </td>
        </tr>
      @empty
        <tr><td colspan="{{ 9 + $weeks }}" class="text-center text-muted p-5">
          No customers have been added to this program yet — {{ $participants }} {{ \Illuminate\Support\Str::plural('participant', $participants) }} enrolled, none have added a target customer.
        </td></tr>
      @endforelse
      </tbody>
    </table>
  </div>
  <div class="card-body pt-3">{{ $paged->links() }}</div>
</div>

@once
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
document.addEventListener('click', function (e) {
  var btn = e.target.closest ? e.target.closest('.ndg-hist-btn') : null;
  if (!btn) { return; }
  var esc = function (s) { return String(s == null ? '' : s).replace(/[&<>"']/g, function (c) { return {'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]; }); };
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
      + (w.notes || []).map(function (n) { return '<div class="small mt-1 ps-2 border-start"><b>' + esc(n.by) + ':</b> ' + esc(n.body) + '</div>'; }).join('')
      + '</div>';
  });
  document.getElementById('ndgHistBody').innerHTML = html;
  if (window.bootstrap && window.bootstrap.Modal) { window.bootstrap.Modal.getOrCreateInstance(document.getElementById('ndgHistModal')).show(); }
});
</script>
@endonce
