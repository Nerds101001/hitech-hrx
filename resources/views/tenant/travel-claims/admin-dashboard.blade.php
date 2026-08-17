@extends('layouts.layoutMaster')

@section('title', 'Travel & Expense Admin Dashboard')

@section('vendor-style')
  @vite([
    'resources/assets/vendor/scss/pages/hitech-portal.scss'
  ])
  <link rel="stylesheet" href="https://cdn.datatables.net/1.13.6/css/dataTables.bootstrap5.min.css">
@endsection


@section('page-style')
<style>
/* Two-line headers keep the summary sheet readable without a 1700px table. */
.ad-h {
    font-size: .68rem; text-transform: uppercase; letter-spacing: .03em;
    font-weight: 700; color: #64748b; background: #f8fafc;
    line-height: 1.25; padding: .5rem .55rem; vertical-align: bottom;
}
.ad-h-sub { font-weight: 500; text-transform: none; letter-spacing: 0; color: #94a3b8; font-size: .62rem; }
#summarySheetTable > tbody > tr > td { vertical-align: middle; font-size: .82rem; padding: .5rem .55rem; }
#summarySheetTable > tbody > tr:hover { background: #f1f5f9; }
#summarySheetTable td:nth-child(2) { line-height: 1.3; }

/* Zebra striping: the claims list had no visual separation between rows. */
/* !important is needed: the theme sets .table-flush-spacing tbody tr to a flat
   white with !important, which is exactly the rule that left the claims list
   looking like one undifferentiated block. */
#claimsTable > tbody > tr:nth-child(even)          { background: #f7fafa !important; }
#claimsTable > tbody > tr:hover,
#claimsTable > tbody > tr:nth-child(even):hover    { background: #e6f4f3 !important; }
/* Real grid lines. .table-flush-spacing strips every border and adds row gaps,
   which is why the list read as floating text with no row/column separation. */
#claimsTable, #summarySheetTable {
    border-collapse: collapse !important;
    border-spacing: 0 !important;
    width: 100%;
}
#claimsTable > thead > tr > th,
#claimsTable > tbody > tr > td,
#summarySheetTable > thead > tr > th,
#summarySheetTable > tbody > tr > td {
    border: 1px solid #e3e9ef !important;
}
#claimsTable > thead > tr > th,
#summarySheetTable > thead > tr > th { border-bottom: 2px solid #cbd5e1 !important; }
#claimsTable > tbody > tr, #summarySheetTable > tbody > tr { box-shadow: none !important; }

/* Pagination — hand-rolled because the page's DataTables never initialises
   (it is pulled from a CDN and calls $().DataTable() with no jQuery present),
   so the table has always been unpaginated. */
.ad-pager { display: flex; align-items: center; gap: .5rem; padding: .7rem .2rem 0; flex-wrap: wrap; }
.ad-pager .sp { margin-left: auto; font-size: .78rem; color: #64748b; }
.ad-pg {
    min-width: 30px; height: 30px; padding: 0 .5rem; border: 1px solid #cbd5e1;
    background: #fff; color: #334155; border-radius: 6px; font-size: .8rem;
    cursor: pointer; transition: background .12s, border-color .12s;
}
.ad-pg:hover:not(:disabled) { background: #f0fdfa; border-color: #5eead4; color: #0f766e; }
.ad-pg[disabled]            { opacity: .45; cursor: default; }
.ad-pg.on                   { background: #0f766e; border-color: #0f766e; color: #fff; font-weight: 700; }

/* Reason boxes on the review panel. These were single-line inputs, so anything
   longer than the field scrolled out of sight and the reviewer could not read
   back what they had written before submitting. Multi-line, and draggable
   taller when the reason runs long — both remarks and objection_notes are TEXT
   columns, so there is no length limit to design around. */
.ad-reason {
    min-height: 78px;
    resize: vertical;
    line-height: 1.45;
    border-radius: 8px;
}
.ad-reason:focus { border-color: #5eead4; box-shadow: 0 0 0 .15rem rgba(15,118,110,.12); }
</style>

<style>
  /* ===================== Custom KPI Purple Card for Admin ===================== */
  .card-purple::after { background: linear-gradient(90deg, #8b5cf6, #c084fc) !important; }
  .icon-purple { background: rgba(139, 92, 246, 0.1); color: #7c3aed; }
</style>
@endsection

@section('content')

<style>
/* ── compact header strip ─────────────────────────────────────────────── */
.ad-head {
    display: flex; align-items: center; gap: 1.25rem; flex-wrap: wrap;
    padding: .85rem 1.25rem; border-radius: 14px;
    background: linear-gradient(90deg, #0d6e6e 0%, #0e8f8f 55%, #12a3a3 100%);
    box-shadow: 0 2px 10px rgba(13,110,110,.18);
}
.ad-head-title { min-width: 190px; }
.ad-head-sub { font-size: .74rem; color: rgba(255,255,255,.72); }
.ad-kpis { display: flex; gap: .5rem; flex-wrap: wrap; margin-left: auto; }
.ad-kpi {
    display: flex; flex-direction: column; align-items: center; justify-content: center;
    min-width: 84px; padding: .35rem .7rem; border-radius: 10px;
    background: rgba(255,255,255,.13); border: 1px solid rgba(255,255,255,.18);
}
.ad-kpi .n { color: #fff; font-weight: 700; font-size: 1.02rem; line-height: 1.15; }
.ad-kpi .l { color: rgba(255,255,255,.75); font-size: .64rem; text-transform: uppercase; letter-spacing: .04em; }
.ad-kpi-money { min-width: 116px; background: rgba(255,255,255,.2); }

/* ── one filter bar ───────────────────────────────────────────────────── */
.ad-filters {
    display: flex; gap: .6rem; align-items: flex-end; flex-wrap: wrap;
    padding: .75rem .85rem; margin-bottom: 1rem;
    background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 10px;
}
.ad-f { display: flex; flex-direction: column; gap: .2rem; }
.ad-f > label {
    font-size: .66rem; font-weight: 700; text-transform: uppercase;
    letter-spacing: .04em; color: #64748b;
}
.ad-in {
    height: 34px; padding: .25rem .55rem; font-size: .82rem;
    border: 1px solid #cbd5e1; border-radius: 8px; background: #fff; color: #0f172a;
    min-width: 150px;
}
.ad-f:first-child .ad-in { min-width: 230px; }
.ad-in:focus { outline: none; border-color: #14b8a6; box-shadow: 0 0 0 3px rgba(20,184,166,.15); }
.ad-reset {
    display: inline-flex; align-items: center; gap: .3rem; height: 34px;
    padding: 0 .8rem; border-radius: 8px; font-size: .8rem; font-weight: 600;
    border: 1px solid #cbd5e1; background: #fff; color: #475569; text-decoration: none;
}
.ad-reset:hover { background: #f1f5f9; color: #0f172a; }
.ad-count { margin-left: auto; font-size: .76rem; color: #64748b; align-self: center; }

/* ── table ────────────────────────────────────────────────────────────── */
#claimsTable > thead > tr > th {
    font-size: .7rem; text-transform: uppercase; letter-spacing: .04em;
    font-weight: 700; color: #64748b; background: #f8fafc;
    border-bottom: 1px solid #e2e8f0; padding: .6rem; white-space: nowrap;
}
#claimsTable > tbody > tr > td { vertical-align: middle; font-size: .85rem; padding: .55rem .6rem; }
#claimsTable > tbody > tr:hover { background: #f1f5f9; }

/* ── icon actions, same language as the claims page ───────────────────── */
.ad-act {
    display: inline-flex; align-items: center; justify-content: center;
    width: 28px; height: 28px; padding: 0; margin-left: 2px;
    border: 1px solid #cbd5e1; border-radius: 6px;
    background: #fff; color: #0f766e; font-size: 15px; line-height: 1;
    cursor: pointer; text-decoration: none;
    transition: background .12s, border-color .12s, color .12s;
}
.ad-act:hover        { background: #f0fdfa; border-color: #5eead4; color: #0f766e; }
.ad-act-review       { color: #0e7490; }
.ad-act-review:hover { background: #ecfeff; border-color: #67e8f9; color: #0e7490; }
.ad-act-dl           { color: #4f46e5; }
.ad-act-dl:hover     { background: #eef2ff; border-color: #a5b4fc; color: #4338ca; }
.ad-act-xls          { color: #15803d; }
.ad-act-xls:hover    { background: #f0fdf4; border-color: #86efac; color: #166534; }
.ad-act:focus-visible { outline: 2px solid #14b8a6; outline-offset: 1px; }

/* Buttons on the teal modal header — white on teal, matching the existing one. */
.ad-modal-btn {
    display: inline-flex; align-items: center; gap: .35rem;
    background: #fff; color: #0f766e; font-weight: 700;
    padding: .4rem .9rem; border-radius: 8px; font-size: .8rem;
    text-decoration: none; white-space: nowrap;
    box-shadow: 0 1px 4px rgba(0,0,0,.15);
}
.ad-modal-btn:hover { background: #f0fdfa; color: #0f766e; }

/* ===== Claim detail sheet: frozen heading row + frozen first three columns =====
   The sheet is 25 columns wide. Scrolled right you lost the date and the party
   visited, so an amount in the middle belonged to no identifiable trip; scrolled
   down you lost the column names. Both panes are now pinned, exactly as the
   exported .xlsx freezes at D2. */
.claim-sheet-wrap {
    max-height: 62vh;
    overflow: auto;                 /* owns BOTH scroll directions */
}
.claim-sheet {
    border-collapse: separate;      /* sticky cells need separate borders */
    border-spacing: 0;
    margin-bottom: 0;
}
.claim-sheet th,
.claim-sheet td {
    border-right: 1px solid #dee2e6;
    border-bottom: 1px solid #dee2e6;
    background: #fff;
    vertical-align: middle;
}
.claim-sheet thead th {
    position: sticky;
    top: 0;
    z-index: 3;
    background: #f1f5f9;
    border-top: 1px solid #dee2e6;
    box-shadow: inset 0 -1px 0 #cbd5e1;
    white-space: nowrap;
}
/* the three pinned columns — offsets must match the widths below */
.claim-sheet th:nth-child(1), .claim-sheet td:nth-child(1) { position: sticky; left: 0;      z-index: 2; min-width: 74px;  max-width: 74px; }
.claim-sheet th:nth-child(2), .claim-sheet td:nth-child(2) { position: sticky; left: 74px;   z-index: 2; min-width: 170px; max-width: 170px; }
.claim-sheet th:nth-child(3), .claim-sheet td:nth-child(3) { position: sticky; left: 244px;  z-index: 2; min-width: 180px; max-width: 180px;
                                                             box-shadow: 2px 0 4px -2px rgba(15,23,42,.25); }
/* corner cells sit above both panes */
.claim-sheet thead th:nth-child(1),
.claim-sheet thead th:nth-child(2),
.claim-sheet thead th:nth-child(3) { z-index: 4; }
.claim-sheet tbody td:nth-child(-n+3) { background: #fff; }
.claim-sheet tbody tr:hover td { background: #f8fafc; }
.claim-sheet tbody tr:hover td:nth-child(-n+3) { background: #f1f5f9; }

@media (max-width: 767.98px) {
    /* Pinning 424px of columns on a 375px screen leaves nothing to read. */
    .claim-sheet th:nth-child(-n+3), .claim-sheet td:nth-child(-n+3) { position: static; max-width: none; }
    .claim-sheet-wrap { max-height: 70vh; }
}
</style>
<div class="animate__animated animate__fadeIn">

  @if(session('success'))
    <div class="alert alert-success alert-dismissible fade show" role="alert">
      <i class="bx bx-check-circle me-2 fs-5 align-middle"></i> {{ session('success') }}
      <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
  @endif

  {{-- One compact header strip. The hero banner, five tall KPI cards and the
       chart between them pushed the actual claims list below the fold; the
       numbers are the same, they just no longer own the screen. --}}
  <div class="ad-head mb-3">
    <div class="ad-head-title">
      <h5 class="mb-0 fw-bold text-white">Travel &amp; Expense</h5>
      <span class="ad-head-sub">All claims, approvals and payouts</span>
    </div>
    <div class="ad-kpis">
      <div class="ad-kpi"><span class="n">{{ number_format($stats['total']) }}</span><span class="l">Total</span></div>
      <div class="ad-kpi"><span class="n">{{ number_format($stats['pending']) }}</span><span class="l">Pending</span></div>
      <div class="ad-kpi"><span class="n">{{ number_format($stats['approved']) }}</span><span class="l">Approved</span></div>
      <div class="ad-kpi"><span class="n">{{ number_format($stats['rejected']) }}</span><span class="l">Rejected</span></div>
      <div class="ad-kpi ad-kpi-money"><span class="n">₹{{ number_format($stats['total_payout']) }}</span><span class="l">Payout</span></div>
    </div>
  </div>

  {{-- Chart and Tables --}}
  <div class="row g-4 mb-4">
    <div class="col-12">
      <div class="hitech-card h-100">
        <div class="hitech-card-header">
          <h5 class="title mb-0 d-flex align-items-center gap-2" style="color: #005a5a;">
            <i class="bx bx-list-ul fs-4 text-primary" style="color: #0d9488 !important;"></i> 
            <span>All Claims List</span>
          </h5>
        </div>
        <div class="card-body p-4">
          {{-- ONE filter bar. Status/Company/Month reload the page because the
               KPI numbers must reflect them; Search and Employee narrow the rows
               already on screen. They used to sit in two separate panels, which
               made it look like two competing filters. --}}
          <form method="GET" action="{{ route('travel-claims.adminDashboard') }}" class="ad-filters" id="filterForm">
            <div class="ad-f">
              <label>Search</label>
              <input type="text" id="dashSearch" class="ad-in" autocomplete="off"
                     placeholder="Name, claim #, company…">
            </div>
            <div class="ad-f">
              <label>Employee</label>
              <select id="dashEmp" class="ad-in">
                <option value="">All employees</option>
                @foreach($claims->pluck('user.name')->filter()->unique()->sort() as $n)
                  <option value="{{ strtolower($n) }}">{{ $n }}</option>
                @endforeach
              </select>
            </div>
            <div class="ad-f">
              <label>Status</label>
              <select name="status" class="ad-in" onchange="this.form.submit()">
                <option value="">All statuses</option>
                @foreach(['submitted'=>'Pending Verification','verified'=>'Verified','approved'=>'Approved','paid'=>'Paid','objection'=>'Objection','rejected'=>'Rejected'] as $k => $v)
                  <option value="{{ $k }}" {{ request('status') == $k ? 'selected' : '' }}>{{ $v }}</option>
                @endforeach
              </select>
            </div>
            <div class="ad-f">
              <label>Company</label>
              <select name="company" class="ad-in" onchange="this.form.submit()">
                <option value="">All companies</option>
                <option value="Hi Tech International" {{ request('company') == 'Hi Tech International' ? 'selected' : '' }}>Hi Tech</option>
                <option value="KEEP IT FRESH LLP" {{ request('company') == 'KEEP IT FRESH LLP' ? 'selected' : '' }}>Keep It Fresh</option>
              </select>
            </div>
            <div class="ad-f">
              <label>Claim month</label>
              <input type="month" name="claim_month" class="ad-in" value="{{ request('claim_month') }}"
                     onchange="this.form.submit()">
            </div>
            <div class="ad-f ad-f-btns">
              <a href="{{ route('travel-claims.adminDashboard') }}" class="ad-reset" title="Clear every filter">
                <i class="bx bx-reset"></i> Reset
              </a>
            </div>
            <div class="ad-count"><span id="dashCount"></span></div>
          </form>

          <div class="table-responsive">
            <table class="table table-flush-spacing mb-0" id="claimsTable">
              <thead class="bg-light">
                <tr>
                  <th class="text-uppercase" data-dsort="id" title="Click to sort" style="font-size: 0.75rem; letter-spacing: 0.5px; cursor:pointer;">ID <i class="bx bx-sort-alt-2 text-muted" style="font-size:.75rem;"></i></th>
                  <th class="text-uppercase" data-dsort="emp" title="Click to sort" style="font-size: 0.75rem; letter-spacing: 0.5px; cursor:pointer;">Employee <i class="bx bx-sort-alt-2 text-muted" style="font-size:.75rem;"></i></th>
                  <th class="text-uppercase" data-dsort="month" title="Click to sort" style="font-size: 0.75rem; letter-spacing: 0.5px; cursor:pointer;">Month <i class="bx bx-sort-alt-2 text-muted" style="font-size:.75rem;"></i></th>
                  <th class="text-uppercase" data-dsort="net" title="Click to sort" style="font-size: 0.75rem; letter-spacing: 0.5px; cursor:pointer;">Net Payable <i class="bx bx-sort-alt-2 text-muted" style="font-size:.75rem;"></i></th>
                  <th class="text-uppercase" style="font-size: 0.75rem; letter-spacing: 0.5px;">Remarks / Objection</th>
                  <th class="text-uppercase" data-dsort="status" title="Click to sort" style="font-size: 0.75rem; letter-spacing: 0.5px; cursor:pointer;">Status <i class="bx bx-sort-alt-2 text-muted" style="font-size:.75rem;"></i></th>
                  <th class="text-uppercase" style="font-size: 0.75rem; letter-spacing: 0.5px;">Actions</th>
                </tr>
              </thead>
              <tbody>
                @forelse($claims as $claim)
                  {{-- Keys on the row so filtering/sorting never parses formatted
                       cell text (currency symbols, badges, line breaks). --}}
                  <tr class="dash-row"
                      data-id="{{ $claim->id }}"
                      data-emp="{{ strtolower($claim->user->name ?? '') }}"
                      data-month="{{ $claim->claim_month }}"
                      data-company="{{ strtolower($claim->company) }}"
                      data-status="{{ $claim->status }}"
                      data-net="{{ (float) $claim->net_payable }}">
                    <td>#{{ $claim->id }}</td>
                    <td>
                      <strong>{{ $claim->user->name }}</strong><br>
                      <small class="text-muted">{{ $claim->company }}</small>
                    </td>
                    <td>{{ $claim->claim_month }}</td>
                    <td>₹{{ number_format($claim->net_payable, 2) }}</td>
                    <td>
                        @if($claim->objection_notes)
                            <div class="text-danger fw-bold"><i class="bx bx-error-circle"></i> {{ $claim->objection_notes }}</div>
                        @elseif($claim->remarks)
                            <div class="text-muted small">{{ $claim->remarks }}</div>
                        @else
                            <span class="text-muted">-</span>
                        @endif
                    </td>
                    <td>
                      <span class="badge bg-{{ $claim->status == 'submitted' ? 'info' : ($claim->status == 'approved' ? 'success' : 'secondary') }}">
                        {{ ucfirst($claim->status) }}
                      </span>
                    </td>
                    <td>
                      <div class="d-flex gap-2">
                        @if($claim->status == 'submitted' || $claim->status == 'verified')
                          <button class="ad-act ad-act-review" data-bs-toggle="modal" data-bs-target="#actionModal{{ $claim->id }}" title="Review this claim"><i class="bx bx-check-shield"></i></button>
                        @else
                          <button class="ad-act" data-bs-toggle="modal" data-bs-target="#actionModal{{ $claim->id }}" title="View details"><i class="bx bx-show"></i></button>
                        @endif
                        <a href="{{ route('travel-claims.download-attachments', $claim->id) }}" class="ad-act ad-act-dl" title="Download all attachments">
                          <i class="bx bx-download"></i>
                        </a>
                        {{-- The claim as a spreadsheet: every line, every
                             amount and a totals row, for checking off-screen. --}}
                        <a href="{{ route('travel-claims.export', $claim->id) }}" class="ad-act ad-act-xls" title="Export this claim to Excel">
                          <i class="bx bx-spreadsheet"></i>
                        </a>
                      </div>
                    </td>
                  </tr>
                @empty
                  <tr>
                    <td colspan="7" class="text-center py-5">No claims found.</td>
                  </tr>
                @endforelse
              </tbody>
            </table>
          </div>
        </div>
      </div>
  </div>

  {{-- Split Payments Summary Sheet --}}
  <div class="row g-4 mb-4">
    <div class="col-12">
      <div class="hitech-card h-100">
        <div class="hitech-card-header bg-light">
          <h5 class="title mb-0 d-flex align-items-center gap-2" style="color: #005a5a;">
            <i class="bx bx-spreadsheet fs-4 text-primary" style="color: #0d9488 !important;"></i> 
            <span>Split Payments Summary Sheet</span>
          </h5>
        </div>
        <div class="card-body p-4">
          <div class="table-responsive">
            <table class="table table-bordered mb-0 align-middle" id="summarySheetTable" style="font-size: 0.85rem;">
              <thead class="bg-light">
                <tr>
                  <th class="ad-h" style="width:80px;">Claim<br>ID</th>
                  <th class="ad-h" style="width:190px;">Employee</th>
                  <th class="ad-h" style="width:90px;">Claim<br>Month</th>
                  <th class="ad-h text-end" style="width:120px;">Total Net<br>Payable</th>
                  <th class="ad-h text-end" style="width:120px;">85% Split<br><span class="ad-h-sub">11th next month</span></th>
                  <th class="ad-h" style="width:120px;">85% Payout<br>Status</th>
                  <th class="ad-h text-end" style="width:120px;">15% Split<br><span class="ad-h-sub">25th next month</span></th>
                  <th class="ad-h" style="width:120px;">15% Payout<br>Status</th>
                </tr>
              </thead>
              <tbody>
                @php
                  $payoutClaims = $claims->filter(function($c) {
                      return in_array($c->status, ['approved', 'paid']);
                  });
                @endphp
                @forelse($payoutClaims as $pClaim)
                  <tr>
                    <td><strong>#{{ $pClaim->id }}</strong></td>
                    <td>
                      <strong>{{ $pClaim->user->name }}</strong><br>
                      <small class="text-muted">{{ $pClaim->company }}</small>
                    </td>
                    <td>{{ $pClaim->claim_month }}</td>
                    <td class="fw-bold">₹{{ number_format($pClaim->net_payable, 2) }}</td>
                    <td>
                      <div><strong>₹{{ number_format($pClaim->split_85_amount, 2) }}</strong></div>
                      <small class="text-muted">Due: {{ $pClaim->split_85_paid_on ? \Carbon\Carbon::parse($pClaim->split_85_paid_on)->format('d M, Y') : '11th' }}</small>
                    </td>
                    <td>
                      @if($pClaim->split_85_transaction)
                        <span class="badge bg-success" data-bs-toggle="tooltip" title="TXN: {{ $pClaim->split_85_transaction }}">Paid</span>
                      @else
                        <span class="badge bg-warning text-dark">Pending Payout</span>
                      @endif
                    </td>
                    <td>
                      <div><strong>₹{{ number_format($pClaim->split_15_amount, 2) }}</strong></div>
                      <small class="text-muted">Due: {{ $pClaim->split_15_paid_on ? \Carbon\Carbon::parse($pClaim->split_15_paid_on)->format('d M, Y') : '25th' }}</small>
                    </td>
                    <td>
                      @if($pClaim->split_15_transaction)
                        <span class="badge bg-success" data-bs-toggle="tooltip" title="TXN: {{ $pClaim->split_15_transaction }}">Paid</span>
                      @else
                        <span class="badge bg-warning text-dark">Pending Payout</span>
                      @endif
                    </td>
                  </tr>
                @empty
                  <tr>
                    <td colspan="8" class="text-center py-4 text-muted">No approved or paid claims for payout splits.</td>
                  </tr>
                @endforelse
              </tbody>
            </table>
          </div>
        </div>
      </div>
    </div>
  </div>

  {{-- Render Modals --}}
  @foreach($claims as $claim)
    <!-- Action Modal -->
    <div class="modal fade" id="actionModal{{ $claim->id }}" tabindex="-1">
      <div class="modal-dialog modal-xl">
        <div class="modal-content border-0 shadow-lg">
          <div class="modal-header">
            <h5 class="modal-title text-white">Claim #{{ $claim->id }} - {{ $claim->user->name }}</h5>
            <div class="d-flex align-items-center gap-2 ms-auto me-2">
              <a href="{{ route('travel-claims.download-attachments', $claim->id) }}" class="ad-modal-btn">
                <i class="bx bx-download"></i> Download Attachments
              </a>
              <a href="{{ route('travel-claims.export', $claim->id) }}" class="ad-modal-btn">
                <i class="bx bx-spreadsheet"></i> Export to Excel
              </a>
            </div>
            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
          </div>
          <div class="modal-body p-0">
            <div class="p-3 bg-white border-bottom">
              <div class="row">
                <div class="col-md-4">
                  <p class="mb-1"><strong>Month:</strong> {{ $claim->claim_month }}</p>
                  <p class="mb-1"><strong>Company:</strong> {{ $claim->company }}</p>
                </div>
                <div class="col-md-4">
                  @php
                    // Full account number only for Accounts, who actually execute
                    // payment — hr/admin/travel_claim.verify can reach this page
                    // to verify claims but don't need the raw number for that.
                    $acctNo = $claim->bank_account_no ?? '';
                    $acctNoShown = auth()->user()->hasRole('accounts')
                        ? $acctNo
                        : (strlen($acctNo) > 4 ? str_repeat('•', strlen($acctNo) - 4) . substr($acctNo, -4) : $acctNo);
                  @endphp
                  <p class="mb-1"><strong>Bank:</strong> {{ $claim->bank_account_name }} ({{ $acctNoShown }})</p>
                  <p class="mb-1"><strong>IFSC:</strong> {{ $claim->bank_ifsc }}</p>
                </div>
                <div class="col-md-4 text-end">
                  <p class="mb-1"><strong>Gross Total:</strong> ₹{{ $claim->total_amount }}</p>
                  <p class="mb-1"><strong>Advances:</strong> ₹{{ $claim->total_advances }}</p>
                  <h5 class="text-primary mb-0"><strong>Net Payable:</strong> ₹{{ $claim->net_payable }}</h5>
                </div>
              </div>
            </div>

            <div class="claim-sheet-wrap">
                <table class="table claim-sheet mb-0" style="font-size: 0.8rem;">
                    <thead>
                        <tr>
                            <th>Date</th>
                            <th>Locations</th>
                            <th>Party</th>
                            <th>Mode & KMs</th>
                            <th>Odometer Photo</th>
                            <th>Petrol Slip</th>
                            <th>Conveyance</th>
                            <th>Auto/Taxi Amount</th>
                            <th>Auto/Taxi Proof</th>
                            <th>Food</th>
                            <th>Lodging</th>
                            <th>Courier</th>
                            <th>Courier Proof</th>
                            <th>Transport</th>
                            <th>Transport Proof</th>
                            <th>Bills</th>
                            <th>Bills Proof</th>
                            <th>Freight</th>
                            <th>Freight Proof</th>
                            <th>Toll</th>
                            <th>Toll Proof</th>
                            <th>Additional Food</th>
                            <th>Additional Food Proof</th>
                            <th>Special Approval</th>
                            <th>Special Approval Proof</th>
                        </tr>
                    </thead>
                    <tbody>
                        @if(isset($claim->items))
                        @foreach($claim->items as $item)
                        <tr>
                            <td>{{ \Carbon\Carbon::parse($item->date)->format('d M') }}</td>
                            <td>{{ $item->from_location }} -> {{ $item->to_location }}</td>
                            <td>{{ $item->party_visited }}</td>
                            <td>{{ $item->mode_of_travel }} <br> <strong>{{ $item->distance_km }} KM</strong></td>
                            <td>
                                @if($item->photo_path)
                                    <button type="button" data-url="{{ \Illuminate\Support\Facades\Storage::url($item->photo_path) }}" class="btn btn-xs btn-outline-primary btn-preview-attachment">View</button>
                                @else
                                     <span class="text-muted">-</span>
                                @endif
                            </td>
                            <td>
                                @if($item->petrol_slip_proof)
                                    <button type="button" data-url="{{ \Illuminate\Support\Facades\Storage::url($item->petrol_slip_proof) }}" class="btn btn-xs btn-outline-primary btn-preview-attachment">View</button>
                                @else
                                     <span class="text-muted">-</span>
                                @endif
                            </td>
                            <td>₹{{ number_format($item->conveyance_amount, 2) }}
                                @if($item->penalty_applied) <br><span class="text-danger small">No Photo Penalty</span> @endif
                            </td>
                            <td>₹{{ number_format($item->auto_taxi_amount ?? 0, 2) }}</td>
                            <td>
                                @if($item->auto_taxi_proof)
                                    <button type="button" data-url="{{ \Illuminate\Support\Facades\Storage::url($item->auto_taxi_proof) }}" class="btn btn-xs btn-outline-primary btn-preview-attachment">View</button>
                                @else
                                     <span class="text-muted">-</span>
                                @endif
                            </td>
                            <td>₹{{ $item->food_allowance }}</td>
                            <td>₹{{ $item->lodging_amount }}</td>
                            <td>₹{{ $item->courier_amount }}</td>
                            <td>
                                @if($item->courier_proof)
                                    <button type="button" data-url="{{ \Illuminate\Support\Facades\Storage::url($item->courier_proof) }}" class="btn btn-xs btn-outline-info btn-preview-attachment">View</button>
                                @else
                                    <span class="text-muted">-</span>
                                @endif
                            </td>
                            <td>₹{{ $item->transport_amount }}</td>
                            <td>
                                @if($item->transport_proof)
                                    <button type="button" data-url="{{ \Illuminate\Support\Facades\Storage::url($item->transport_proof) }}" class="btn btn-xs btn-outline-info btn-preview-attachment">View</button>
                                @else
                                    <span class="text-muted">-</span>
                                @endif
                            </td>
                            <td>₹{{ $item->bills_amount }}</td>
                            <td>
                                @if($item->bills_proof)
                                    <button type="button" data-url="{{ \Illuminate\Support\Facades\Storage::url($item->bills_proof) }}" class="btn btn-xs btn-outline-info btn-preview-attachment">View</button>
                                @else
                                    <span class="text-muted">-</span>
                                @endif
                            </td>
                            <td>₹{{ $item->freight_amount }}</td>
                            <td>
                                @if($item->freight_proof)
                                    <button type="button" data-url="{{ \Illuminate\Support\Facades\Storage::url($item->freight_proof) }}" class="btn btn-xs btn-outline-info btn-preview-attachment">View</button>
                                @else
                                    <span class="text-muted">-</span>
                                @endif
                            </td>
                            <td>₹{{ $item->toll_amount }}</td>
                            <td>
                                @if($item->toll_proof)
                                    <button type="button" data-url="{{ \Illuminate\Support\Facades\Storage::url($item->toll_proof) }}" class="btn btn-xs btn-outline-info btn-preview-attachment">View</button>
                                @else
                                    <span class="text-muted">-</span>
                                @endif
                            </td>
                            <td>₹{{ $item->additional_food_amount ?? 0 }}</td>
                            <td>
                                @if($item->additional_food_proof)
                                    <button type="button" data-url="{{ \Illuminate\Support\Facades\Storage::url($item->additional_food_proof) }}" class="btn btn-xs btn-outline-info btn-preview-attachment">View</button>
                                @else
                                    <span class="text-muted">-</span>
                                @endif
                            </td>
                            <td>₹{{ $item->special_approval_amount ?? 0 }}</td>
                            <td>
                                @if($item->special_approval_proof)
                                    <button type="button" data-url="{{ \Illuminate\Support\Facades\Storage::url($item->special_approval_proof) }}" class="btn btn-xs btn-outline-info btn-preview-attachment">View</button>
                                @else
                                    <span class="text-muted">-</span>
                                @endif
                            </td>
                        </tr>
                        @endforeach
                        @endif
                    </tbody>
                </table>
            </div>

            <div class="p-3 border-top">
            @if(in_array($claim->status, ['submitted', 'verified']))
              <!-- Approve/Verify -->
              @if($claim->status == 'submitted')
              <form action="{{ route('travel-claims.verify', $claim->id) }}" method="POST" class="mb-3">
                  @csrf
                  <div class="d-flex gap-2 align-items-end">
                    <textarea name="remarks" class="form-control ad-reason" rows="2" placeholder="Verification Remarks (Optional)"></textarea>
                    <button type="submit" class="btn btn-success text-nowrap" style="border-radius: 8px;">Approve & Verify</button>
                  </div>
              </form>
              @endif

              <div class="row g-3">
                  <div class="col-6">
                      <form action="{{ route('travel-claims.objection', $claim->id) }}" method="POST">
                          @csrf
                          <div class="d-flex gap-2 align-items-end">
                            <textarea name="remarks" class="form-control ad-reason" rows="3" placeholder="Objection Reason" required></textarea>
                            <button type="submit" class="btn btn-warning text-nowrap" style="border-radius: 8px;">Raise Objection</button>
                          </div>
                      </form>
                  </div>
                  <div class="col-6">
                      <form action="{{ route('travel-claims.reject', $claim->id) }}" method="POST">
                          @csrf
                          <div class="d-flex gap-2 align-items-end">
                            <textarea name="remarks" class="form-control ad-reason" rows="3" placeholder="Rejection Reason" required></textarea>
                            <button type="submit" class="btn btn-danger text-nowrap" style="border-radius: 8px;">Reject Claim</button>
                          </div>
                      </form>
                  </div>
              </div>
            @else
              <div class="alert alert-info mb-0">
                  Status: <strong>{{ ucfirst($claim->status) }}</strong><br>
                  Remarks: {{ $claim->remarks ?? 'N/A' }}
              </div>
            @endif
            </div>
          </div>
        </div>
      </div>
    </div>
  @endforeach

</div>

<!-- Attachment Preview Modal -->
<div class="modal fade" id="attachmentPreviewModal" tabindex="-1" aria-hidden="true" style="z-index: 1060;">
    <div class="modal-dialog modal-xl modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header bg-dark py-3">
                <h5 class="modal-title text-white fw-bold mb-0">Attachment Preview</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body text-center p-3" id="previewModalBody">
                <div class="spinner-border text-primary" role="status">
                    <span class="visually-hidden">Loading...</span>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

@section('page-script')
<script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>
<script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.13.6/js/dataTables.bootstrap5.min.js"></script>
<script>
  $(document).ready(function() {
    $('#claimsTable').DataTable({
      order: [[0, 'asc']],
      pageLength: 25,
      language: {
        search: "_INPUT_",
        searchPlaceholder: "Search claims..."
      }
    });

    // Attachment Preview Listener
    document.addEventListener('click', function(e) {
        const btn = e.target.closest('.btn-preview-attachment');
        if (btn) {
            e.preventDefault();
            const url = btn.getAttribute('data-url');
            const filename = url.split('/').pop();
            const ext = filename.split('.').pop().toLowerCase();
            
            let html = '';
            if (['jpg', 'jpeg', 'png', 'gif', 'webp', 'bmp'].includes(ext)) {
                html = '<img src="' + url + '" class="img-fluid rounded shadow-sm" style="max-height: 75vh;" />';
            } else if (ext === 'pdf') {
                html = '<iframe src="' + url + '" style="width: 100%; height: 75vh; border: none;"></iframe>';
            } else {
                html = '<div class="p-5 text-center">' +
                       '<i class="bx bx-file-blank fs-1 text-muted mb-3"></i>' +
                       '<h5>Cannot Preview Directly</h5>' +
                       '<p class="text-muted">This file format (.' + ext + ') cannot be previewed in your browser.</p>' +
                       '<a href="' + url + '" target="_blank" class="btn btn-primary rounded-pill px-4 mt-2" download>' +
                       '<i class="bx bx-download me-1"></i> Download File' +
                       '</a>' +
                       '</div>';
            }
            
            document.getElementById('previewModalBody').innerHTML = html;
            bootstrap.Modal.getOrCreateInstance(document.getElementById('attachmentPreviewModal')).show();
        }
    });
  });
</script>

<script>
/* All Claims List: instant search, employee filter and column sorting. */
(function () {
    const table = document.getElementById('claimsTable');
    if (!table) return;
    const tbody  = table.querySelector('tbody');
    const search = document.getElementById('dashSearch');
    const emp    = document.getElementById('dashEmp');
    const count  = document.getElementById('dashCount');
    if (!tbody || !search) return;

    const rows  = () => Array.from(tbody.querySelectorAll('tr.dash-row'));
    const total = rows().length;

    function apply() {
        const q = (search.value || '').trim().toLowerCase();
        const e = emp.value;
        let shown = 0;

        rows().forEach(function (tr) {
            const hay = tr.dataset.emp + ' #' + tr.dataset.id + ' ' +
                        tr.dataset.company + ' ' + tr.dataset.month + ' ' + tr.dataset.status;
            const ok = (!q || hay.includes(q)) && (!e || tr.dataset.emp === e);
            tr.style.display = ok ? '' : 'none';
            if (ok) shown++;
        });

        count.textContent = shown === total
            ? 'Showing all ' + total + ' claim(s)'
            : 'Showing ' + shown + ' of ' + total + ' claim(s)';

        // Keep the "no claims" placeholder out of a filtered list.
        const empty = tbody.querySelector('tr:not(.dash-row)');
        if (empty) empty.style.display = (total && shown === 0) ? '' : (total ? 'none' : '');
    }

    const dir = {};
    table.querySelectorAll('th[data-dsort]').forEach(function (th) {
        th.addEventListener('click', function () {
            const key = th.dataset.dsort;
            dir[key] = !dir[key];
            const numeric = (key === 'id' || key === 'net');
            rows().sort(function (a, b) {
                const x = a.dataset[key], y = b.dataset[key];
                const r = numeric ? (parseFloat(x) - parseFloat(y)) : String(x).localeCompare(String(y));
                return dir[key] ? r : -r;
            }).forEach(tr => tbody.appendChild(tr));

            table.querySelectorAll('th[data-dsort] i').forEach(i => i.className = 'bx bx-sort-alt-2 text-muted');
            const icon = th.querySelector('i');
            if (icon) icon.className = dir[key] ? 'bx bx-up-arrow-alt' : 'bx bx-down-arrow-alt';
        });
    });

    search.addEventListener('input', apply);
    emp.addEventListener('change', apply);
    document.getElementById('dashClear').addEventListener('click', function () {
        search.value = ''; emp.value = ''; apply();
    });

    apply();
})();
</script>

<script>
/* Filter + paginate the claims list. One system: the filter decides which rows
   are eligible, the pager decides which of those are on screen. */
(function () {
    const tbody = document.querySelector('#claimsTable tbody');
    if (!tbody) return;
    const search = document.getElementById('dashSearch');
    const emp    = document.getElementById('dashEmp');
    const count  = document.getElementById('dashCount');
    const rows   = () => Array.from(tbody.querySelectorAll('tr.dash-row'));
    const total  = rows().length;

    let page = 1, perPage = 25;

    let pager = document.getElementById('adPager');
    if (!pager) {
        pager = document.createElement('div');
        pager.id = 'adPager';
        pager.className = 'ad-pager';
        tbody.closest('table').parentNode.appendChild(pager);
    }

    function eligible() {
        const q = (search?.value || '').trim().toLowerCase();
        const e = emp?.value || '';
        return rows().filter(tr => {
            const hay = tr.dataset.emp + ' #' + tr.dataset.id + ' ' +
                        tr.dataset.company + ' ' + tr.dataset.month + ' ' + tr.dataset.status;
            return (!q || hay.includes(q)) && (!e || tr.dataset.emp === e);
        });
    }

    function draw() {
        const list = eligible();
        const pages = Math.max(1, Math.ceil(list.length / perPage));
        if (page > pages) page = pages;
        const from = (page - 1) * perPage;
        const to = Math.min(from + perPage, list.length);

        rows().forEach(tr => tr.style.display = 'none');
        list.slice(from, to).forEach(tr => tr.style.display = '');

        if (count) {
            count.textContent = list.length === total
                ? 'Showing all ' + total + ' claim(s)'
                : 'Showing ' + list.length + ' of ' + total + ' claim(s)';
        }

        let html = '';
        html += '<button class="ad-pg" ' + (page === 1 ? 'disabled' : '') + ' data-go="prev">\u2039</button>';
        for (let i = 1; i <= pages; i++) {
            if (pages > 7 && i > 3 && i < pages - 2 && Math.abs(i - page) > 1) {
                if (html.slice(-16) !== '<span>\u2026</span>') html += '<span>\u2026</span>';
                continue;
            }
            html += '<button class="ad-pg ' + (i === page ? 'on' : '') + '" data-go="' + i + '">' + i + '</button>';
        }
        html += '<button class="ad-pg" ' + (page === pages ? 'disabled' : '') + ' data-go="next">\u203a</button>';
        html += '<select class="ad-pg" id="adPer" style="width:auto;">'
             +  [10, 25, 50, 100].map(n => '<option value="' + n + '"' + (n === perPage ? ' selected' : '') + '>' + n + ' / page</option>').join('')
             +  '</select>';
        html += '<span class="sp">' + (list.length ? (from + 1) + '\u2013' + to : 0) + ' of ' + list.length + '</span>';
        pager.innerHTML = html;
    }

    pager.addEventListener('click', e => {
        const b = e.target.closest('[data-go]');
        if (!b || b.disabled) return;
        const v = b.dataset.go;
        const pages = Math.max(1, Math.ceil(eligible().length / perPage));
        page = v === 'prev' ? Math.max(1, page - 1)
             : v === 'next' ? Math.min(pages, page + 1) : parseInt(v, 10);
        draw();
    });
    pager.addEventListener('change', e => {
        if (e.target.id === 'adPer') { perPage = parseInt(e.target.value, 10); page = 1; draw(); }
    });
    [search, emp].forEach(el => el && el.addEventListener('input', () => { page = 1; draw(); }));
    if (emp) emp.addEventListener('change', () => { page = 1; draw(); });

    draw();
})();
</script>
@endsection
