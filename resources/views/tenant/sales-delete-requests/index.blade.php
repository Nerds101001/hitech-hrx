@extends('layouts/layoutMaster')

@section('title', 'Customer Deletion Requests')

@section('vendor-style')
  @vite([
    'resources/assets/vendor/scss/pages/hitech-portal.scss',
    'resources/assets/vendor/libs/sweetalert2/sweetalert2.scss'
  ])
@endsection

@section('page-style')
<style>
  /* Everything here is built from the portal theme's own variables
     (--hitech-*) and its .icon-sophisticated action button, so this page
     matches the rest of HRX rather than inventing its own palette. */

  /* ── compact stat strip ─────────────────────────────────────────────── */
  .dr-stats {
    display: grid;
    grid-template-columns: repeat(6, minmax(0, 1fr));
    gap: 0.65rem;
    margin-bottom: 1rem;
  }
  @media (max-width: 1199px) { .dr-stats { grid-template-columns: repeat(3, minmax(0, 1fr)); } }
  @media (max-width: 575px)  { .dr-stats { grid-template-columns: repeat(2, minmax(0, 1fr)); } }
  .dr-stat {
    background: #fff;
    border: 1px solid var(--hitech-border);
    border-left: 3px solid var(--dr-accent, var(--hitech-primary));
    border-radius: 12px;
    padding: 0.7rem 0.85rem;
    display: flex; align-items: center; gap: 0.6rem;
    text-decoration: none;
    transition: transform .2s cubic-bezier(0.4, 0, 0.2, 1);
  }
  .dr-stat:hover  { transform: translateY(-2px); }
  .dr-stat.active { background: var(--hitech-primary-light); border-color: var(--dr-accent, var(--hitech-primary)); }
  .dr-stat .ico {
    width: 28px; height: 28px; border-radius: 8px; flex-shrink: 0;
    display: inline-flex; align-items: center; justify-content: center;
    background: var(--hitech-primary-light);
    color: var(--dr-accent, var(--hitech-primary));
    font-size: 0.95rem;
  }
  .dr-stat .v { font-size: 1.1rem; font-weight: 800; color: #1e293b; line-height: 1.15; }
  .dr-stat .l { font-size: 0.62rem; font-weight: 700; text-transform: uppercase;
                letter-spacing: 0.03em; color: #64748b; white-space: nowrap;
                overflow: hidden; text-overflow: ellipsis; }

  /* ── filter bar ─────────────────────────────────────────────────────── */
  .dr-filters {
    background: #fff;
    border: 1px solid var(--hitech-border);
    border-radius: 12px;
    padding: 0.85rem 1rem;
    margin-bottom: 1rem;
  }
  .dr-filters label {
    font-size: 0.66rem; font-weight: 700; text-transform: uppercase;
    letter-spacing: .04em; color: #64748b; margin-bottom: .2rem; display: block;
  }
  .dr-filters .form-select,
  .dr-filters .form-control {
    height: 36px; font-size: 0.82rem; border-radius: 8px;
    border-color: var(--hitech-border);
  }
  .dr-filters .form-select:focus,
  .dr-filters .form-control:focus {
    border-color: var(--hitech-primary);
    box-shadow: 0 0 0 3px var(--hitech-primary-light);
  }
  .btn-dr {
    height: 36px; border-radius: 8px; font-size: 0.82rem; font-weight: 700;
    background: var(--hitech-primary); color: #fff; border: none;
    display: inline-flex; align-items: center; gap: .35rem; padding: 0 .9rem;
  }
  .btn-dr:hover { background: var(--primary-teal-dark, #005a5a); color: #fff; }

  /* ── table ──────────────────────────────────────────────────────────── */
  .dr-card { background: #fff; border: 1px solid var(--hitech-border); border-radius: 12px; overflow: hidden; }
  .dr-table thead th {
    background: var(--hitech-bg-soft); color: #1e293b;
    font-size: .7rem; font-weight: 800; text-transform: uppercase; letter-spacing: .04em;
    padding: .7rem .75rem; white-space: nowrap; border-bottom: 2px solid var(--hitech-border);
  }
  .dr-table thead th a { color: inherit; text-decoration: none; }
  .dr-table thead th a:hover { color: var(--hitech-primary); }
  .dr-table td { padding: .65rem .75rem; font-size: .84rem; vertical-align: middle;
                 border-bottom: 1px solid var(--hitech-border); color: #374151; }
  .dr-table tbody tr:hover { background: var(--hitech-bg-soft); }
  .dr-table tbody tr.selected { background: var(--hitech-primary-light); }
  .sort-ind { font-size: .7rem; opacity: .5; }

  /* ── selection bar: only appears once something is ticked ───────────── */
  .dr-bulkbar {
    display: none;
    align-items: center; gap: .75rem; flex-wrap: wrap;
    padding: .6rem .9rem; margin-bottom: .75rem;
    background: var(--hitech-primary-light);
    border: 1px solid var(--hitech-primary);
    border-radius: 10px;
  }
  .dr-bulkbar.show { display: flex; }
  .dr-bulkbar .n { font-weight: 800; color: var(--hitech-primary); }

  .dr-act {
    --dr-act-accent: var(--hitech-primary);
    background: var(--hitech-bg-soft);
    color: #64748b;
    border: 1px solid var(--hitech-border);
  }
  .dr-act:hover {
    background: #fff;
    color: var(--dr-act-accent);
    border-color: var(--dr-act-accent);
  }
  .dr-act i { transition: transform .2s ease; }
  .dr-act:hover i { transform: scale(1.1); }
  .dr-act-approve { --dr-act-accent: var(--hitech-icon-deactivate); }  /* it deletes — theme red */
  .dr-act-reject  { --dr-act-accent: var(--hitech-primary); }

  .dr-reason { font-size: .7rem; font-weight: 700; padding: .25em .7em; border-radius: 20px; }

  @media (max-width: 767.98px) {
    .dr-table thead { display: none; }
    .dr-table, .dr-table tbody, .dr-table tr, .dr-table td { display: block; width: 100%; }
    .dr-table tr { border: 1px solid var(--hitech-border); border-radius: 12px;
                   margin: 0 0 .75rem; padding: .3rem .1rem; background: #fff; }
    .dr-table td { display: flex; justify-content: space-between; gap: 1rem;
                   border-bottom: 1px dashed var(--hitech-border); padding: .55rem .85rem; }
    .dr-table td:last-child { border-bottom: none; }
    .dr-table td::before {
      content: attr(data-label); font-size: .66rem; font-weight: 800;
      text-transform: uppercase; letter-spacing: .04em; color: #94a3b8; flex: 0 0 34%;
    }
    .dr-act { width: 38px; height: 38px; }
  }
</style>
@endsection

@section('content')
@php
    $reasonAccent = [
        'duplicate'  => '#f5a623',
        'legal_case' => '#ef4444',
        'closed'     => '#64748b',
    ];
    $reasonBadge = [
        'duplicate'  => 'bg-label-warning',
        'legal_case' => 'bg-label-danger',
        'closed'     => 'bg-label-secondary',
    ];
    // Preserve every filter when a sort link or tab is clicked.
    $carry = request()->except(['sort', 'dir', 'page']);
    $sortLink = function ($key) use ($carry, $sort, $dir) {
        $next = ($sort === $key && $dir === 'asc') ? 'desc' : 'asc';
        return route('sales-visits.pipeline.delete-requests.index', array_merge($carry, ['sort' => $key, 'dir' => $next]));
    };
    $sortInd = fn($key) => $sort === $key ? ($dir === 'asc' ? '▲' : '▼') : '⇅';
@endphp

<div class="animate__animated animate__fadeIn">

  {{-- Hero --}}
  <div class="hitech-page-hero mb-4">
    <div class="hitech-page-hero-text d-flex justify-content-between align-items-center w-100 flex-wrap gap-3">
      <div>
        <h4 class="greeting">Customer Deletion Requests</h4>
        <p class="sub-text mb-0">Approving a request deletes the customer and every monthly figure attached to it. It cannot be undone.</p>
      </div>
      <a href="{{ route('sales-visits.pipeline.index') }}"
         class="btn btn-light rounded-pill px-4 fw-bold shadow-sm flex-shrink-0"
         style="background-color:#ffffff !important; color:#0d7377 !important;">
        <i class="bx bx-arrow-back me-1"></i> Back to Sales Report
      </a>
    </div>
  </div>

  @if(session('success'))
    <div class="alert alert-success alert-dismissible py-2">{{ session('success') }}
      <button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
  @endif
  @if(session('error'))
    <div class="alert alert-danger alert-dismissible py-2">{{ session('error') }}
      <button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
  @endif

  {{-- Status + category totals. Each card is a link that applies its own
       filter, so the numbers are a way in rather than just decoration. --}}
  <div class="dr-stats">
    @foreach([
      ['pending',  'Pending',  'bx-time-five',      '#f5a623'],
      ['approved', 'Approved', 'bx-check-circle',   '#10b981'],
      ['rejected', 'Rejected', 'bx-x-circle',       '#ef4444'],
    ] as [$k, $label, $icon, $accent])
      <a class="dr-stat {{ $tab === $k ? 'active' : '' }}" style="--dr-accent: {{ $accent }};"
         href="{{ route('sales-visits.pipeline.delete-requests.index', ['tab' => $k]) }}">
        <span class="ico" style="background: {{ $accent }}1a; color: {{ $accent }};"><i class="bx {{ $icon }}"></i></span>
        <div class="min-w-0">
          <div class="v">{{ number_format($counts[$k] ?? 0) }}</div>
          <div class="l">{{ $label }}</div>
        </div>
      </a>
    @endforeach

    {{-- Category-wise, within the tab being viewed --}}
    @foreach($reasons as $key => $label)
      <a class="dr-stat {{ request('reason') === $key ? 'active' : '' }}"
         style="--dr-accent: {{ $reasonAccent[$key] ?? '#64748b' }};"
         href="{{ route('sales-visits.pipeline.delete-requests.index', array_merge($carry, ['reason' => request('reason') === $key ? null : $key])) }}">
        <span class="ico" style="background: {{ $reasonAccent[$key] ?? '#64748b' }}1a; color: {{ $reasonAccent[$key] ?? '#64748b' }};">
          <i class="bx bx-purchase-tag"></i>
        </span>
        <div class="min-w-0">
          <div class="v">{{ number_format($reasonCounts[$key] ?? 0) }}</div>
          <div class="l" title="{{ $label }} ({{ ucfirst($tab) }})">{{ $label }}</div>
        </div>
      </a>
    @endforeach
  </div>

  {{-- Filters --}}
  <form method="GET" action="{{ route('sales-visits.pipeline.delete-requests.index') }}" class="dr-filters" id="drFilters">
    <input type="hidden" name="tab" value="{{ $tab }}">
    <div class="row g-2 align-items-end">
      <div class="col-12 col-md-3 col-lg-2">
        <label for="drQ">Customer</label>
        <input type="text" id="drQ" name="q" class="form-control" value="{{ request('q') }}" placeholder="Search name…">
      </div>
      <div class="col-6 col-md-3 col-lg-2">
        <label for="drRep">Salesperson / CCare / New Biz</label>
        <select id="drRep" name="rep" class="form-select">
          <option value="">Anyone</option>
          @foreach($reps as $id => $name)
            <option value="{{ $id }}" {{ (string) request('rep') === (string) $id ? 'selected' : '' }}>{{ $name }}</option>
          @endforeach
        </select>
      </div>
      <div class="col-6 col-md-3 col-lg-2">
        <label for="drReq">Requested by</label>
        <select id="drReq" name="requested_by" class="form-select">
          <option value="">Anyone</option>
          @foreach($requesters as $id => $name)
            <option value="{{ $id }}" {{ (string) request('requested_by') === (string) $id ? 'selected' : '' }}>{{ $name }}</option>
          @endforeach
        </select>
      </div>
      <div class="col-6 col-md-3 col-lg-2">
        <label for="drAct">Deleted / decided by</label>
        <select id="drAct" name="actioned_by" class="form-select">
          <option value="">Anyone</option>
          @foreach($actioners as $id => $name)
            <option value="{{ $id }}" {{ (string) request('actioned_by') === (string) $id ? 'selected' : '' }}>{{ $name }}</option>
          @endforeach
        </select>
      </div>
      <div class="col-6 col-md-3 col-lg-1">
        <label for="drReason">Reason</label>
        <select id="drReason" name="reason" class="form-select">
          <option value="">All</option>
          @foreach($reasons as $key => $label)
            <option value="{{ $key }}" {{ request('reason') === $key ? 'selected' : '' }}>{{ $label }}</option>
          @endforeach
        </select>
      </div>
      <div class="col-6 col-md-3 col-lg-1">
        <label for="drBook">Book</label>
        <select id="drBook" name="book" class="form-select">
          <option value="">Both</option>
          <option value="report"   {{ request('book') === 'report' ? 'selected' : '' }}>Sales Report</option>
          <option value="pipeline" {{ request('book') === 'pipeline' ? 'selected' : '' }}>Sales Pipeline</option>
        </select>
      </div>
      <div class="col-12 col-lg-2 d-flex gap-2">
        <button type="submit" class="btn-dr flex-grow-1 justify-content-center">
          <i class="bx bx-filter-alt"></i> Filter
        </button>
        <a href="{{ route('sales-visits.pipeline.delete-requests.index', ['tab' => $tab]) }}"
           class="icon-sophisticated dr-act" title="Clear all filters" aria-label="Clear all filters"
           style="width:36px;height:36px;">
          <i class="bx bx-reset"></i>
        </a>
      </div>
    </div>
  </form>

  {{-- Bulk action bar --}}
  @if($tab === 'pending')
    <form method="POST" action="{{ route('sales-visits.pipeline.delete-requests.bulk') }}" id="drBulkForm">
      @csrf
      {{-- bulk_action, not "action": a field named "action" shadows the form's
           own action property, so form.action returns the input element
           instead of the URL. --}}
      <input type="hidden" name="bulk_action" id="drBulkAction">
      <input type="hidden" name="decision_note" id="drBulkNote">
      <div class="dr-bulkbar" id="drBulkBar">
        <span><span class="n" id="drCount">0</span> selected</span>
        <button type="button" class="btn btn-danger btn-sm rounded-pill px-3 fw-semibold" id="drBulkApprove">
          <i class="bx bx-trash me-1"></i> Approve &amp; delete selected
        </button>
        <button type="button" class="btn btn-outline-secondary btn-sm rounded-pill px-3 fw-semibold" id="drBulkReject">
          <i class="bx bx-x me-1"></i> Reject selected
        </button>
        <a href="javascript:void(0);" class="ms-auto small text-muted" id="drClearSel">Clear selection</a>
      </div>
  @endif

  <div class="dr-card">
    <div class="table-responsive">
      <table class="table dr-table mb-0">
        <thead>
          <tr>
            @if($tab === 'pending')
              <th style="width:38px;">
                <input class="form-check-input" type="checkbox" id="drAll" title="Select every request on this page">
              </th>
            @endif
            <th><a href="{{ $sortLink('customer') }}">Customer <span class="sort-ind">{{ $sortInd('customer') }}</span></a></th>
            <th><a href="{{ $sortLink('book') }}">Book <span class="sort-ind">{{ $sortInd('book') }}</span></a></th>
            <th><a href="{{ $sortLink('reason') }}">Reason <span class="sort-ind">{{ $sortInd('reason') }}</span></a></th>
            <th>Remarks</th>
            <th>Salesperson / CCare / New Biz</th>
            <th>Requested by</th>
            <th><a href="{{ $sortLink('requested') }}">Requested <span class="sort-ind">{{ $sortInd('requested') }}</span></a></th>
            @if($tab === 'pending')
              <th class="text-end" style="width:90px;">Action</th>
            @else
              <th>Decided by</th>
              <th><a href="{{ $sortLink('decided') }}">Decided <span class="sort-ind">{{ $sortInd('decided') }}</span></a></th>
              @if($tab === 'rejected')<th>Note</th>@endif
            @endif
          </tr>
        </thead>
        <tbody>
        @forelse($requests as $r)
          @php
            $p    = $r->pipeline;
            $reps = array_filter([
                $p?->salesperson?->name ? $p->salesperson->name . ' (SP)'    : null,
                $p?->ccare?->name       ? $p->ccare->name . ' (CC)'          : null,
                $p?->newBiz?->name      ? $p->newBiz->name . ' (NB)'         : null,
            ]);
          @endphp
          <tr>
            @if($tab === 'pending')
              <td data-label="Select">
                <input class="form-check-input dr-check" type="checkbox" name="ids[]" value="{{ $r->id }}"
                       form="drBulkForm" aria-label="Select {{ $r->party_name }}">
              </td>
            @endif
            <td data-label="Customer" class="fw-bold text-dark">{{ $r->party_name }}</td>
            <td data-label="Book"><span class="badge bg-label-info">{{ $r->book_label }}</span></td>
            <td data-label="Reason">
              <span class="dr-reason badge {{ $reasonBadge[$r->reason] ?? 'bg-label-secondary' }}">{{ $r->reason_label }}</span>
            </td>
            <td data-label="Remarks" class="text-muted" style="max-width:240px;">{{ $r->remarks ?: '—' }}</td>
            <td data-label="Salesperson / CCare / New Biz" class="text-muted" style="font-size:.78rem;">
              {{ $reps ? implode(' · ', $reps) : '—' }}
            </td>
            <td data-label="Requested by">{{ $r->requester?->getFullName() ?? '#' . $r->requested_by }}</td>
            <td data-label="Requested" class="text-muted text-nowrap">{{ $r->created_at?->format('d M Y, H:i') }}</td>

            @if($tab === 'pending')
              <td data-label="Action" class="text-end text-nowrap">
                <div class="d-inline-flex gap-1">
                  <button type="button" class="icon-sophisticated dr-act dr-act-approve js-approve-delete"
                          data-id="{{ $r->id }}" data-party="{{ $r->party_name }}"
                          title="Approve and delete" aria-label="Approve and delete {{ $r->party_name }}">
                    <i class="bx bx-trash"></i>
                  </button>
                  <button type="button" class="icon-sophisticated dr-act dr-act-reject js-reject-delete"
                          data-id="{{ $r->id }}" data-party="{{ $r->party_name }}"
                          title="Reject — keep the customer" aria-label="Reject request for {{ $r->party_name }}">
                    <i class="bx bx-x"></i>
                  </button>
                </div>
              </td>
            @else
              <td data-label="Decided by">{{ $r->actioner?->getFullName() ?? '—' }}</td>
              <td data-label="Decided" class="text-muted text-nowrap">{{ $r->actioned_at?->format('d M Y, H:i') ?? '—' }}</td>
              @if($tab === 'rejected')<td data-label="Note" class="text-muted">{{ $r->decision_note ?: '—' }}</td>@endif
            @endif
          </tr>
        @empty
          <tr>
            <td colspan="12" class="text-center text-muted py-5">
              <i class="bx bx-inbox d-block mb-2" style="font-size:2.5rem;opacity:.35;"></i>
              No {{ $tab }} deletion requests match these filters.
            </td>
          </tr>
        @endforelse
        </tbody>
      </table>
    </div>

    @if($requests->hasPages())
      <div class="d-flex justify-content-center p-3 border-top">{{ $requests->appends(request()->query())->links() }}</div>
    @endif
  </div>

  @if($tab === 'pending')
    </form>
  @endif
</div>

{{-- Single-row actions post through these, so one code path per decision. --}}
<form id="approveForm" method="POST" style="display:none;">@csrf</form>
<form id="rejectForm"  method="POST" style="display:none;">@csrf<input type="hidden" name="decision_note" id="rejectNote"></form>
@endsection

@section('vendor-script')
  @vite(['resources/assets/vendor/libs/sweetalert2/sweetalert2.js'])
@endsection

@section('page-script')
<script>
/* Wait for DOMContentLoaded: the layout loads its JS through @@vite as
   deferred modules, so anything touching a global at parse time runs before
   that global exists and silently kills the rest of the script. */
document.addEventListener('DOMContentLoaded', function () {

  const BASE = @json(url('sales-visits/delete-requests'));
  const esc  = s => String(s || '').replace(/</g, '&lt;');

  // ── selection ──────────────────────────────────────────────────────────
  const bar    = document.getElementById('drBulkBar');
  const countN = document.getElementById('drCount');
  const all    = document.getElementById('drAll');
  const boxes  = () => Array.from(document.querySelectorAll('.dr-check'));
  const picked = () => boxes().filter(b => b.checked);

  function refresh() {
    const n = picked().length;
    if (countN) countN.textContent = n;
    if (bar) bar.classList.toggle('show', n > 0);
    boxes().forEach(b => b.closest('tr')?.classList.toggle('selected', b.checked));
    if (all) {
      all.checked       = n > 0 && n === boxes().length;
      all.indeterminate = n > 0 && n < boxes().length;
    }
  }

  document.addEventListener('change', function (e) {
    if (e.target === all) { boxes().forEach(b => b.checked = all.checked); refresh(); }
    else if (e.target.classList?.contains('dr-check')) { refresh(); }
  });

  document.getElementById('drClearSel')?.addEventListener('click', function () {
    boxes().forEach(b => b.checked = false); refresh();
  });

  // ── confirmations ──────────────────────────────────────────────────────
  // Approving is a real, irreversible delete, so it always asks — and the
  // bulk version says how many customers are about to go.
  function confirmDelete(title, bodyHtml, onYes) {
    if (typeof Swal === 'undefined') { if (confirm(title)) onYes(); return; }
    Swal.fire({
      title: title,
      html: '<div style="font-size:.9rem;color:#566a7f;line-height:1.55;">' + bodyHtml
          + '<div style="margin-top:10px;color:#c0392b;font-weight:700;">This cannot be reverted.</div></div>',
      showCancelButton: true, reverseButtons: true, focusCancel: true, buttonsStyling: false,
      confirmButtonText: 'Yes, delete', cancelButtonText: 'Cancel',
      customClass: { confirmButton: 'btn btn-danger me-2', cancelButton: 'btn btn-outline-secondary' }
    }).then(r => { if (r.isConfirmed) onYes(); });
  }

  function askNote(title, bodyHtml, onYes) {
    if (typeof Swal === 'undefined') { onYes(prompt('Reason for rejecting?') || ''); return; }
    Swal.fire({
      title: title,
      input: 'textarea',
      inputLabel: 'Why is it being kept? (optional)',
      inputPlaceholder: 'e.g. not a duplicate — different unit',
      html: '<div style="font-size:.88rem;color:#566a7f;">' + bodyHtml + '</div>',
      showCancelButton: true, reverseButtons: true, buttonsStyling: false,
      confirmButtonText: 'Reject', cancelButtonText: 'Cancel',
      customClass: { confirmButton: 'btn btn-primary me-2', cancelButton: 'btn btn-outline-secondary' }
    }).then(r => { if (r.isConfirmed) onYes(r.value); });
  }

  // ── single row ─────────────────────────────────────────────────────────
  document.addEventListener('click', function (e) {
    const a = e.target.closest('.js-approve-delete');
    if (a) {
      confirmDelete('Approve and delete?',
        '<div style="font-weight:700;color:#2f3944;margin-bottom:8px;">' + esc(a.dataset.party) + '</div>'
        + 'This deletes the customer and <b>every monthly figure</b> attached to it.',
        () => {
          const f = document.getElementById('approveForm');
          f.action = BASE + '/' + a.dataset.id + '/approve';
          f.submit();
        });
      return;
    }

    const j = e.target.closest('.js-reject-delete');
    if (j) {
      askNote('Reject this request?', esc(j.dataset.party) + ' will be kept.', note => {
        const f = document.getElementById('rejectForm');
        f.action = BASE + '/' + j.dataset.id + '/reject';
        document.getElementById('rejectNote').value = note || '';
        f.submit();
      });
    }
  });

  // ── bulk ───────────────────────────────────────────────────────────────
  const bulkForm = document.getElementById('drBulkForm');

  document.getElementById('drBulkApprove')?.addEventListener('click', function () {
    const rows = picked();
    if (!rows.length) return;
    const names = rows.slice(0, 6).map(b => esc(b.getAttribute('aria-label').replace(/^Select /, '')));
    confirmDelete('Delete ' + rows.length + ' customer' + (rows.length > 1 ? 's' : '') + '?',
      '<div style="font-weight:700;color:#2f3944;margin-bottom:8px;">' + names.join('<br>')
      + (rows.length > names.length ? '<br>and ' + (rows.length - names.length) + ' more' : '') + '</div>'
      + 'Each one loses <b>every monthly figure</b> attached to it.',
      () => {
        document.getElementById('drBulkAction').value = 'approve';
        bulkForm.submit();
      });
  });

  document.getElementById('drBulkReject')?.addEventListener('click', function () {
    const rows = picked();
    if (!rows.length) return;
    askNote('Reject ' + rows.length + ' request' + (rows.length > 1 ? 's' : '') + '?',
      'The customers will be kept. The note is saved against every one of them.',
      note => {
        document.getElementById('drBulkAction').value = 'reject';
        document.getElementById('drBulkNote').value = note || '';
        bulkForm.submit();
      });
  });

  refresh();
});
</script>
@endsection
