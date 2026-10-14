@extends('layouts.layoutMaster')

@section('title', 'Mind Speak Dashboard')

@section('vendor-style')
  @vite([
    'resources/assets/vendor/scss/pages/hitech-portal.scss'
  ])
@endsection

@section('page-style')
<style>
  /* ===================== Chart & Tables Card ===================== */
  .ms-card {
    background: #fff;
    border-radius: 16px;
    box-shadow: 0 4px 24px rgba(0,0,0,0.06);
    border: none;
    margin-bottom: 1.5rem;
  }
  .ms-card-header {
    padding: 1.25rem 1.6rem;
    font-weight: 700;
    color: #1e293b;
    font-size: 1rem;
    border-bottom: 1px solid var(--hitech-border);
    display: flex;
    justify-content: space-between;
    align-items: center;
  }
  .ms-card-title {
    margin: 0;
    font-weight: 700;
    color: #1e293b;
  }

  /* ===================== Filter Bar ===================== */
  .filter-bar {
    background: #fff;
    border-radius: 14px;
    padding: 1.2rem 1.6rem;
    box-shadow: 0 4px 20px rgba(0,0,0,0.05);
    margin-bottom: 1.5rem;
    border: none;
  }
  .filter-bar .form-label {
    font-size: 0.78rem;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    color: #4b5563;
    margin-bottom: 0.3rem;
  }
  .filter-bar .form-select,
  .filter-bar .form-control {
    border-radius: 9px;
    font-size: 0.88rem;
    border-color: var(--hitech-border);
    height: 40px;
  }
  .filter-bar .btn-filter {
    background: var(--hitech-primary);
    color: #fff;
    border: none;
    border-radius: 9px;
    font-weight: 700;
    height: 40px;
    font-size: 0.88rem;
    transition: background 0.2s;
  }
  .filter-bar .btn-filter:hover {
    background: var(--primary-teal-dark, #005a5a);
  }
  .filter-bar .btn-export {
    background: var(--hitech-icon-password);
    color: #fff;
    border: none;
    border-radius: 9px;
    font-weight: 700;
    height: 40px;
    font-size: 0.88rem;
    transition: background 0.2s;
  }
  .filter-bar .btn-export:hover {
    background: #0f9c6e;
  }

  /* ===================== Submissions Table ===================== */
  .ms-table thead th {
    background: var(--hitech-bg-soft);
    color: #1e293b;
    font-size: 0.78rem;
    font-weight: 800;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    border-bottom: 2px solid var(--hitech-border);
    padding: 1rem;
  }
  .ms-table tbody tr {
    transition: background 0.15s ease;
  }
  .ms-table tbody tr:hover {
    background: var(--hitech-bg-soft);
  }
  .ms-table td {
    padding: 1rem;
    vertical-align: middle;
    font-size: 0.88rem;
    color: #374151;
    border-bottom: 1px solid var(--hitech-border);
  }
  
  .ms-badge {
    display: inline-block;
    padding: 0.35em 0.85em;
    border-radius: 20px;
    font-size: 0.75rem;
    font-weight: 700;
    letter-spacing: 0.3px;
  }
  .badge-idea { background: #e0e7ff; color: #3730a3; }
  .badge-complaint  { background: #fee2e2; color: #991b1b; }
  .badge-sales { background: #fef3c7; color: #92400e; }
  .badge-seminar { background: #dcfce7; color: #166534; }
  .badge-competitor { background: #e0e7ff; color: #4338ca; }
  .badge-kaizen { background: #fef9c3; color: #854d0e; }
  .badge-360 { background: #f3e8ff; color: #6b21a8; }

  .emp-code-badge {
    background: var(--hitech-primary-light);
    color: var(--hitech-primary);
    font-weight: 700;
    padding: 0.25rem 0.6rem;
    border-radius: 6px;
    font-size: 0.8rem;
    border: 1px solid var(--hitech-border);
  }

  /* ===================== Compact stat strip =====================
     The old cards were a full dashboard tile each and pushed the table
     below the fold. Seven of these fit on one row.

     Colours come from the portal theme (--hitech-*), not from a palette
     invented for this page: one teal accent throughout, the theme's soft
     background and the theme's border. */
  /* auto-fit + a real minmax floor, not a fixed repeat(7): the old fixed
     7-column grid gave a 1366px laptop the exact same column width as a
     1920px desktop (no breakpoint existed between 1200px and desktop),
     which is why "COMPETITOR INSIGHTS" / "360 FEEDBACK" collided on
     laptop too, not just on phone. 150px is the floor each .ms-stat needs
     (28px icon + gap + padding + the shortest label) before its own
     ellipsis takes over instead of visibly overlapping its neighbour. */
  .ms-stat-strip {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
    gap: 0.65rem;
    margin-bottom: 1.25rem;
  }
  @media (max-width: 575px)  { .ms-stat-strip { grid-template-columns: repeat(2, minmax(0, 1fr)); } }
  .ms-stat {
    background: #fff;
    border: 1px solid var(--hitech-border);
    border-left: 3px solid var(--hitech-primary);
    border-radius: 12px;
    padding: 0.7rem 0.85rem;
    display: flex;
    align-items: center;
    gap: 0.6rem;
    transition: transform .2s cubic-bezier(0.4, 0, 0.2, 1);
  }
  .ms-stat:hover { transform: translateY(-2px); }
  .ms-stat .ico {
    width: 28px; height: 28px; border-radius: 8px; flex-shrink: 0;
    display: inline-flex; align-items: center; justify-content: center;
    background: var(--hitech-primary-light);
    color: var(--hitech-primary);
    font-size: 0.95rem;
  }
  .ms-stat .v { font-size: 1.1rem; font-weight: 800; color: #1e293b; line-height: 1.15; }
  .ms-stat .l { font-size: 0.62rem; font-weight: 700; text-transform: uppercase;
                letter-spacing: 0.03em; color: #64748b; white-space: nowrap;
                overflow: hidden; text-overflow: ellipsis; }

  /* ===================== Icon action buttons =====================
     Text buttons wrapped and pushed the table wide; the reviewer's
     remarks made it worse.

     These extend the theme's own .icon-sophisticated (slate at rest,
     lifting to colour on hover) rather than introducing a new set of
     pastel fills. The accent on hover uses the theme's action colours. */
  .ms-act {
    --ms-act-accent: var(--hitech-primary);
    background: var(--hitech-bg-soft);
    color: #64748b;
    border: 1px solid var(--hitech-border);
  }
  .ms-act:hover {
    background: #fff;
    color: var(--ms-act-accent);
    border-color: var(--ms-act-accent);
  }
  .ms-act:hover i { transform: scale(1.1); }
  .ms-act i { transition: transform 0.2s ease; }
  .ms-act-view    { --ms-act-accent: var(--hitech-icon-view); }
  .ms-act-approve { --ms-act-accent: var(--hitech-icon-password); }   /* theme green  */
  .ms-act-ack     { --ms-act-accent: var(--hitech-primary); }         /* theme teal   */
  .ms-act-reject  { --ms-act-accent: var(--hitech-icon-deactivate); } /* theme red    */

  .ms-remark-flag { color: var(--hitech-primary); cursor: help; font-size: 0.95rem; }

  /* ===================== Mobile =====================
     A seven-column table inside a horizontal scroller is unusable on a
     phone. Below 768px each row becomes a stacked card, with the column
     name coming from the td's data-label. */
  @media (max-width: 767.98px) {
    .ms-table thead { display: none; }
    .ms-table, .ms-table tbody, .ms-table tr, .ms-table td { display: block; width: 100%; }
    .ms-table tr {
      border: 1px solid var(--hitech-border);
      border-radius: 12px;
      margin: 0 0 0.85rem;
      padding: 0.35rem 0.15rem;
      background: #fff;
    }
    .ms-table td {
      display: flex;
      justify-content: space-between;
      align-items: flex-start;
      gap: 1rem;
      border-bottom: 1px dashed var(--hitech-border);
      padding: 0.6rem 0.9rem;
      text-align: left !important;
    }
    .ms-table td:last-child { border-bottom: none; }
    .ms-table td::before {
      content: attr(data-label);
      font-size: 0.68rem;
      font-weight: 800;
      text-transform: uppercase;
      letter-spacing: 0.4px;
      color: #94a3b8;
      flex: 0 0 34%;
    }
    .ms-table td > * { text-align: right; }
    .ms-table td[data-label="Content"] { display: block; }
    .ms-table td[data-label="Content"] > div { max-width: none !important; text-align: left; }
    .ms-table td[data-label="Content"]::before { display: block; margin-bottom: 0.3rem; }
    .ms-table td[data-label="Actions"] { justify-content: space-between; }
    .table-responsive { overflow-x: visible; }
    .ms-act { width: 38px; height: 38px; }   /* comfortable tap target */
  }

  /* ===================== Alerts ===================== */
  .ms-alert {
    border-radius: 12px;
    border-left: 5px solid;
    padding: 0.9rem 1.2rem;
    margin-bottom: 1.5rem;
    font-size: 0.9rem;
    font-weight: 500;
  }
</style>
@endsection

@section('content')
<div class="animate__animated animate__fadeIn">

  {{-- Flash Messages --}}
  @if(session('success'))
    <div class="ms-alert alert alert-success alert-dismissible fade show" role="alert" style="border-left-color: #10b981;">
      <i class="bx bx-check-circle me-2 fs-5 align-middle"></i> {{ session('success') }}
      <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
  @endif
  @if(session('error'))
    <div class="ms-alert alert alert-danger alert-dismissible fade show" role="alert" style="border-left-color: #ef4444;">
      <i class="bx bx-error-circle me-2 fs-5 align-middle"></i> {{ session('error') }}
      <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
  @endif

  {{-- Hero Header --}}
  <div class="hitech-page-hero mb-6 animate__animated animate__fadeIn">
    <div class="hitech-page-hero-text d-flex justify-content-between align-items-center w-100 flex-wrap gap-3">
      <div>
        @if($kaizenOnly ?? false)
          <h4 class="greeting">Kaizen Approvals</h4>
          <p class="sub-text mb-0">Kaizen / improvement proposals routed to you for approval.</p>
        @else
          <h4 class="greeting">Mind Speak Dashboard</h4>
          <p class="sub-text mb-0">Review ideas, feedback, complaints, sales pitches, seminars, Kaizens and 360° feedback submitted by staff members.</p>
        @endif
      </div>
      {{-- Admin and HR landed here with no way to post anything themselves. --}}
      <a href="{{ route('mind-speak.index', ['compose' => 1]) }}"
         class="btn btn-light rounded-pill px-4 fw-bold shadow-sm flex-shrink-0"
         style="background-color:#ffffff !important; color:#0d7377 !important;">
        <i class="bx bx-plus-circle me-1"></i> Submit an Entry
      </a>
    </div>
  </div>

  {{-- KPI strip — one compact row so the submissions table stays above the fold --}}
  <div class="ms-stat-strip">
    @foreach(($kaizenOnly ?? false) ? [
      ['bx-bulb',          'Total Kaizens', $stats['kaizen'] ?? 0],
      ['bx-time-five',     'Pending',       $stats['kaizen_pending'] ?? 0],
      ['bx-check-circle',  'Approved',      $stats['kaizen_approved'] ?? 0],
      ['bx-x-circle',      'Rejected',      $stats['kaizen_rejected'] ?? 0],
    ] : [
      ['bx-brain',        'Total Speaks',        $stats['total']],
      ['bx-bulb',         'Ideas',               $stats['idea']],
      ['bx-microphone',   'Sales Pitches',       $stats['sales_pitch'] ?? 0],
      ['bx-presentation', 'Seminars',            $stats['seminar'] ?? 0],
      ['bx-bullseye',     'Competitor Insights', $stats['competitor_insights'] ?? 0],
      ['bx-cog',          'Kaizens',             $stats['kaizen'] ?? 0],
      ['bx-transfer',     '360° Feedback',       $stats['feedback_360'] ?? 0],
      ['bx-error-circle', 'Complaints',          $stats['complaint']],
      ['bx-hide',         'Anonymous',           $stats['anonymous']],
    ] as [$icon, $label, $value])
      <div class="ms-stat">
        <span class="ico"><i class="bx {{ $icon }}"></i></span>
        <div class="min-w-0">
          <div class="v">{{ number_format($value) }}</div>
          <div class="l" title="{{ $label }}">{{ $label }}</div>
        </div>
      </div>
    @endforeach
  </div>

  {{-- Filter & Sort Form --}}
  <div class="filter-bar">
    <form method="GET" action="{{ route('mind-speak.index') }}" id="filterForm">
      <div class="row g-3 align-items-end">
        @unless($kaizenOnly ?? false)
        <div class="col-12 col-sm-6 col-md-2">
          <label class="form-label">Category</label>
          <select name="category" class="form-select">
            <option value="">All Categories</option>
            <option value="Idea" {{ request('category') == 'Idea' ? 'selected' : '' }}>Idea</option>
            <option value="Complaint" {{ request('category') == 'Complaint' ? 'selected' : '' }}>Complaint</option>
            <option value="Sales Pitch" {{ request('category') == 'Sales Pitch' ? 'selected' : '' }}>Sales Pitch</option>
            <option value="Seminar" {{ request('category') == 'Seminar' ? 'selected' : '' }}>Seminar</option>
            <option value="Competitor Insights" {{ request('category') == 'Competitor Insights' ? 'selected' : '' }}>Competitor Insights</option>
            <option value="Kaizen" {{ request('category') == 'Kaizen' ? 'selected' : '' }}>Kaizen</option>
            <option value="360° Feedback" {{ request('category') == '360° Feedback' ? 'selected' : '' }}>360° Feedback</option>
          </select>
        </div>
        @endunless

        <div class="col-12 col-sm-6 col-md-2">
          <label class="form-label">Visibility</label>
          <select name="is_anonymous" class="form-select">
            <option value="">All Submissions</option>
            <option value="1" {{ request('is_anonymous') == '1' ? 'selected' : '' }}>Anonymous Only</option>
            <option value="0" {{ request('is_anonymous') == '0' ? 'selected' : '' }}>Public Only</option>
          </select>
        </div>

        <div class="col-12 col-sm-6 col-md-2">
          <label class="form-label">Department</label>
          <select name="department_id" class="form-select">
            <option value="">All Departments</option>
            @foreach($departments as $dept)
              <option value="{{ $dept->id }}" {{ request('department_id') == $dept->id ? 'selected' : '' }}>
                {{ $dept->name }}
              </option>
            @endforeach
          </select>
        </div>

        <div class="col-12 col-sm-6 col-md-2">
          <label class="form-label">From Date</label>
          <input type="date" name="date_from" class="form-control" value="{{ request('date_from') }}">
        </div>

        <div class="col-12 col-sm-6 col-md-2">
          <label class="form-label">To Date</label>
          <input type="date" name="date_to" class="form-control" value="{{ request('date_to') }}">
        </div>

        <div class="col-12 col-md-2">
          {{-- This cell is only ~1/6 of the row (col-md-2); Filter plus two
               fixed 40px icon buttons never fit un-wrapped at laptop width,
               which is why Export got pushed off-screen there. flex-wrap +
               Filter on its own full-width row fixes it without touching
               the row's other 5 filter cells. --}}
          <div class="d-flex gap-2 flex-wrap">
            <button type="submit" class="btn btn-filter fw-bold" style="flex: 1 1 100%;">
              <i class="bx bx-filter-alt me-1"></i> Filter
            </button>
            <a href="{{ route('mind-speak.index') }}" class="btn btn-outline-secondary d-flex align-items-center justify-content-center" style="border-radius: 9px; height: 40px; width: 40px; min-width: 40px; padding: 0; flex-shrink: 0;" title="Reset Filters">
              <i class="bx bx-reset fs-5"></i>
            </a>
            {{-- Export is admin/HR only (MindSpeakController::export) — Vijender/
                 Manoj's access is Kaizen-review only, so don't offer a button
                 that would just 403 for them. --}}
            @unless($kaizenOnly ?? false)
            <button type="button" id="msExportBtn" class="btn btn-export d-flex align-items-center justify-content-center" style="border-radius: 9px; height: 40px; width: 40px; min-width: 40px; padding: 0; flex-shrink: 0;" title="Export to CSV">
              <i class="bx bx-download fs-5"></i>
            </button>
            @endunless
          </div>
        </div>
      </div>
    </form>
  </div>

  {{-- The monthly trend chart and the department breakdown were removed: the
       reviewer's job here is to act on submissions, and both pushed the table
       below the fold. Dropping the chart also removes the Chart.js CDN. --}}

  {{-- Detailed Table --}}
  <div class="card ms-card">
    <div class="ms-card-header d-flex justify-content-between align-items-center">
      <span><i class="bx bx-list-ul me-2 text-primary"></i> Detailed Submissions List</span>
      <small class="text-muted">Showing {{ $submissions->firstItem() ?? 0 }}–{{ $submissions->lastItem() ?? 0 }} of {{ $submissions->total() }} submissions</small>
    </div>
    <div class="card-body p-0">
      @if($submissions->count() > 0)
        <div class="table-responsive">
          <table class="table ms-table mb-0">
            <thead>
              <tr>
                {{-- Employee ID, Submitter and Department were three columns
                     saying the same thing; folded into one to give the content
                     and the actions room. --}}
                <th scope="col">Category</th>
                <th scope="col">Content / Idea</th>
                <th scope="col">Submitter</th>
                <th scope="col">Submitted</th>
                <th scope="col">Status</th>
                <th scope="col" class="text-end">Actions</th>
              </tr>
            </thead>
            <tbody>
              @foreach($submissions as $sub)
                <tr>
                  <td data-label="Category">
                    @php
                      $badgeClass = match($sub->category) {
                        'Idea' => 'badge-idea',
                        'Complaint' => 'badge-complaint',
                        'Sales Pitch' => 'badge-sales',
                        'Seminar' => 'badge-seminar',
                        'Competitor Insights' => 'badge-competitor',
                        'Kaizen' => 'badge-kaizen',
                        '360° Feedback' => 'badge-360',
                        default => 'badge-other',
                      };
                      $msBadge = match($sub->status ?? 'pending') {
                        'approved'     => 'bg-label-success',
                        'acknowledged' => 'bg-label-info',
                        'rejected'     => 'bg-label-danger',
                        default        => 'bg-label-secondary',
                      };
                      // Review: a proposal (Sales Pitch / Seminar / Competitor
                      // Insights) is approved or rejected; feedback (Idea /
                      // Complaint) is acknowledged with a reply. Either way a
                      // rejection must carry a reason.
                      $msActions = $sub->availableActions();
                      $msIcon = ['approve' => 'bx-check', 'acknowledge' => 'bx-message-rounded-check', 'reject' => 'bx-x'];
                      $msTone = ['approve' => 'approve',  'acknowledge' => 'ack',                      'reject' => 'reject'];
                    @endphp
                    <span class="ms-badge {{ $badgeClass }}">{{ $sub->category }}</span>
                  </td>
                  <td data-label="Content">
                    <div style="max-width: 320px; min-width: 180px; font-size: 0.85rem; line-height: 1.5; color: #374151;">
                      {{ \Illuminate\Support\Str::limit($sub->content, 140) }}
                      @if(strlen($sub->content) > 140)
                        <a href="javascript:void(0);" class="text-primary fw-bold ms-1" data-bs-toggle="modal" data-bs-target="#viewModalAdmin{{ $sub->id }}">Read Details</a>
                      @endif
                    </div>

                    {{-- Detail Modal --}}
                    <div class="modal fade" id="viewModalAdmin{{ $sub->id }}" tabindex="-1" aria-hidden="true">
                      <div class="modal-dialog modal-dialog-centered modal-lg">
                        <div class="modal-content">
                          <div class="modal-header">
                            <h5 class="modal-title d-flex align-items-center">
                              <span class="ms-badge {{ $badgeClass }} me-3">{{ $sub->category }}</span> 
                              Mind Speak Submission Details
                            </h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                          </div>
                          <div class="modal-body p-4">
                            <div class="row g-4 mb-4">
                              <div class="col-md-6">
                                <span class="d-block text-uppercase text-muted fw-bold mb-1" style="font-size: 0.72rem; letter-spacing: 0.5px;">Employee ID</span>
                                {{-- The employee code identifies the person just as well as
                                     their name. Showing it on an anonymous submission — right
                                     next to a label promising anonymity — defeated the whole
                                     feature, so it is masked with everything else. --}}
                                @if($sub->is_anonymous)
                                  <span class="text-muted font-italic">[Anonymous]</span>
                                @else
                                  <span class="emp-code-badge">{{ $sub->user->code }}</span>
                                @endif
                              </div>
                              <div class="col-md-6">
                                <span class="d-block text-uppercase text-muted fw-bold mb-1" style="font-size: 0.72rem; letter-spacing: 0.5px;">Visibility Mode</span>
                                @if($sub->is_anonymous)
                                  <span class="text-warning fw-bold"><i class="bx bx-hide align-middle fs-5 me-1"></i> Anonymous (Name & Department hidden)</span>
                                @else
                                  <span class="text-success fw-bold"><i class="bx bx-show align-middle fs-5 me-1"></i> Public Submitter Details</span>
                                @endif
                              </div>
                              @if(!$sub->is_anonymous)
                                <div class="col-md-6">
                                  <span class="d-block text-uppercase text-muted fw-bold mb-1" style="font-size: 0.72rem; letter-spacing: 0.5px;">Submitter Name</span>
                                  <span class="fw-semibold text-dark">{{ $sub->user->full_name ?? $sub->user->name }}</span>
                                </div>
                                <div class="col-md-6">
                                  <span class="d-block text-uppercase text-muted fw-bold mb-1" style="font-size: 0.72rem; letter-spacing: 0.5px;">Submitter Email</span>
                                  <span class="fw-semibold text-dark">{{ $sub->user->email }}</span>
                                </div>
                                <div class="col-md-6">
                                  <span class="d-block text-uppercase text-muted fw-bold mb-1" style="font-size: 0.72rem; letter-spacing: 0.5px;">Department</span>
                                  <span class="fw-semibold text-dark">{{ $sub->user->department?->name ?? 'N/A' }}</span>
                                </div>
                              @endif
                              <div class="col-md-6">
                                <span class="d-block text-uppercase text-muted fw-bold mb-1" style="font-size: 0.72rem; letter-spacing: 0.5px;">Date Submitted</span>
                                <span class="fw-semibold text-dark">{{ $sub->created_at->format('d M Y, h:i A') }}</span>
                              </div>
                            </div>
                            <hr class="my-4" style="border-color: var(--hitech-border);">
                            <span class="d-block text-uppercase text-muted fw-bold mb-2" style="font-size: 0.72rem; letter-spacing: 0.5px;">Submitted Content / Message</span>
                            <div class="p-3 rounded-3 text-dark" style="white-space: pre-line; line-height: 1.7; font-size: 0.95rem; background: var(--hitech-primary-light); border: 1px solid var(--hitech-border);">
                              {{ $sub->content }}
                            </div>
                            @if($sub->attachment)
                              <div class="mt-4">
                                <span class="d-block text-uppercase text-muted fw-bold mb-2" style="font-size: 0.72rem; letter-spacing: 0.5px;">Attached File / Recording</span>
                                @php
                                  $ext = pathinfo($sub->attachment, PATHINFO_EXTENSION);
                                  $isAudio = in_array(strtolower($ext), ['mp3', 'wav', 'ogg', 'webm', 'm4a', 'mp4']);
                                @endphp
                                @if($isAudio)
                                  <audio controls class="w-100">
                                    <source src="{{ route('mind-speak.attachment', $sub->id) }}" type="audio/{{ $ext == 'webm' ? 'webm' : 'mpeg' }}">
                                    Your browser does not support the audio element.
                                  </audio>
                                @else
                                  <a href="{{ route('mind-speak.attachment', $sub->id) }}" target="_blank" class="btn btn-primary rounded-pill px-4">
                                    <i class="bx bx-download me-1"></i> View Attachment
                                  </a>
                                @endif
                              </div>
                            @endif
                            {{-- The reviewer's own note lives here rather than in
                                 the table, where it was wrapping the column. --}}
                            @if($sub->admin_remarks)
                              <div class="mt-4">
                                <span class="d-block text-uppercase text-muted fw-bold mb-2" style="font-size: 0.72rem; letter-spacing: 0.5px;">
                                  Reviewer's Remarks
                                  @if($sub->reviewed_at)
                                    — {{ $sub->reviewer?->first_name ?? 'Reviewer' }}, {{ $sub->reviewed_at->format('d M Y') }}
                                  @endif
                                </span>
                                <div class="p-3 rounded-3" style="background: var(--hitech-bg-soft); border: 1px solid var(--hitech-border); border-left: 3px solid var(--hitech-primary); white-space: pre-line; font-size: 0.9rem;">
                                  {{ $sub->admin_remarks }}
                                </div>
                              </div>
                            @endif
                          </div>
                          <div class="modal-footer d-flex justify-content-between">
                            <span class="badge {{ $msBadge }}">{{ ucfirst($sub->status ?? 'pending') }}</span>
                            <div class="d-flex gap-2">
                              <button type="button" class="btn btn-outline-secondary rounded-pill px-4" data-bs-dismiss="modal">Close</button>
                              {{-- Acting straight from the detail view: the reviewer
                                   has just read the whole submission, which is the
                                   moment they can actually judge it. --}}
                              @foreach($msActions as $act)
                                <button type="button"
                                        class="btn rounded-pill px-4 fw-semibold ms-review-btn
                                               {{ $act === 'reject' ? 'btn-danger' : ($act === 'approve' ? 'btn-success' : 'btn-primary') }}"
                                        data-id="{{ $sub->id }}" data-action="{{ $act }}"
                                        data-category="{{ $sub->category }}"
                                        data-from-modal="viewModalAdmin{{ $sub->id }}">
                                  <i class="bx {{ $msIcon[$act] }} me-1"></i>{{ ucfirst($act) }}
                                </button>
                              @endforeach
                            </div>
                          </div>
                        </div>
                      </div>
                    </div>

                  </td>
                  {{-- Submitter: code, name, email and department in one column.
                       All four are masked together on an anonymous row — the
                       code identifies a person just as surely as the name. --}}
                  <td data-label="Submitter" style="min-width: 180px;">
                    @if($sub->is_anonymous)
                      <span class="text-muted font-italic small"><i class="bx bx-hide align-middle"></i> [Anonymous]</span>
                    @else
                      <div class="d-flex align-items-center gap-2 mb-1">
                        <span class="emp-code-badge">{{ $sub->user->code }}</span>
                        <span class="fw-semibold text-dark" style="font-size:.86rem;">{{ $sub->user->full_name ?? $sub->user->name }}</span>
                      </div>
                      <small class="text-muted d-block" style="font-size: 0.74rem;">{{ $sub->user->email }}</small>
                      <small class="text-muted d-block" style="font-size: 0.74rem;">{{ $sub->user->department?->name ?? 'N/A' }}</small>
                    @endif
                  </td>
                  <td data-label="Submitted" class="text-nowrap">
                    <span class="text-dark fw-semibold" style="font-size:.83rem;">{{ $sub->created_at->format('d M Y') }}</span><br>
                    <small class="text-muted" style="font-size: 0.72rem;">{{ $sub->created_at->format('h:i A') }}</small>
                  </td>
                  <td data-label="Status" class="text-nowrap">
                    <span class="badge {{ $msBadge }}">{{ ucfirst($sub->status ?? 'pending') }}</span>
                    {{-- The remarks used to be printed in full here and wrapped
                         the column badly; the flag opens the detail view. --}}
                    @if($sub->admin_remarks)
                      <i class="bx bx-message-square-dots ms-remark-flag ms-1 align-middle"
                         title="{{ \Illuminate\Support\Str::limit($sub->admin_remarks, 160) }}"></i>
                    @endif
                    @if($sub->reviewed_at)
                      <div class="text-muted" style="font-size:.68rem;">
                        {{ $sub->reviewer?->first_name ?? '—' }} · {{ $sub->reviewed_at->format('d M') }}
                      </div>
                    @endif
                  </td>
                  <td data-label="Actions" class="text-end text-nowrap">
                    <div class="d-inline-flex gap-1">
                      <button type="button" class="icon-sophisticated ms-act ms-act-view"
                              data-bs-toggle="modal" data-bs-target="#viewModalAdmin{{ $sub->id }}"
                              title="View full submission" aria-label="View full submission">
                        <i class="bx bx-show"></i>
                      </button>
                      @foreach($msActions as $act)
                        <button type="button"
                                class="icon-sophisticated ms-act ms-act-{{ $msTone[$act] }} ms-review-btn"
                                data-id="{{ $sub->id }}" data-action="{{ $act }}"
                                data-category="{{ $sub->category }}"
                                title="{{ ucfirst($act) }}" aria-label="{{ ucfirst($act) }} this submission">
                          <i class="bx {{ $msIcon[$act] }}"></i>
                        </button>
                      @endforeach
                    </div>
                  </td>
                </tr>
              @endforeach
            </tbody>
          </table>
        </div>
        
        {{-- Pagination --}}
        <div class="d-flex justify-content-center align-items-center p-4">
          {{ $submissions->appends(request()->query())->links() }}
        </div>
      @else
        <div class="text-center py-6 text-muted">
          <i class="bx bx-cabinet fs-1 d-block mb-2 text-light" style="font-size: 4rem !important;"></i>
          <p class="mb-0 fw-semibold">No submissions found matching selected filters.</p>
          <a href="{{ route('mind-speak.index') }}" class="btn btn-sm mt-2 btn-outline-secondary" style="border-radius: 9px;">
            Clear Filters
          </a>
        </div>
      @endif
    </div>
  </div>

{{-- Review modal — shared by Approve / Acknowledge / Reject --}}
<div class="modal fade" id="msReviewModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <form method="POST" id="msReviewForm" class="modal-content">
      @csrf
      <input type="hidden" name="action" id="msAction">
      <div class="modal-header">
        <h5 class="modal-title" id="msReviewTitle">Review submission</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">
        <label class="form-label fw-semibold" id="msRemarksLabel" for="msRemarks">Remarks</label>
        <textarea class="form-control" name="remarks" id="msRemarks" rows="4" maxlength="2000"
                  placeholder="What should the employee know?"></textarea>
        <div class="invalid-feedback">A reason is required — the employee cannot fix what they were not told.</div>
        <div class="form-text" id="msRemarksHint">The employee sees this on their Mind Speak page.</div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-outline-secondary rounded-pill px-4" data-bs-dismiss="modal">Cancel</button>
        <button type="submit" class="btn rounded-pill px-4 fw-semibold btn-success" id="msReviewSubmit">Confirm</button>
      </div>
    </form>
  </div>
</div>

</div>
@endsection

@section('page-script')
<script>
  /* Everything here waits for DOMContentLoaded.
   *
   * The layout loads Bootstrap through @@vite, which emits
   * <script type="module"> — and modules are DEFERRED, so they run AFTER this
   * inline script. The previous version called `new bootstrap.Modal(...)` at
   * parse time, which threw "bootstrap is not defined" and killed the rest of
   * the block, so no click handler was ever attached and Approve / Acknowledge
   * / Reject did nothing at all. The detail modal still opened because that
   * one is declarative (data-bs-toggle), which Bootstrap wires up itself
   * whenever it finally loads.
   *
   * Modules are guaranteed to execute before DOMContentLoaded fires, so by the
   * time this runs `window.bootstrap` exists. It is still checked, because a
   * silent no-op is what caused this bug in the first place. */
  document.addEventListener('DOMContentLoaded', function () {

    // ---- CSV export ----
    const exportBtn = document.getElementById('msExportBtn');
    if (exportBtn) {
      exportBtn.addEventListener('click', function () {
        const params = new URLSearchParams(new FormData(document.getElementById('filterForm'))).toString();
        window.location.href = @json(route('mind-speak.export')) + '?' + params;
      });
    }

    if (typeof bootstrap === 'undefined') {
      console.error('Mind Speak: Bootstrap did not load — review actions are unavailable.');
      return;
    }

    // ---- auto-dismiss flash messages ----
    document.querySelectorAll('.ms-alert').forEach(function (el) {
      setTimeout(function () { bootstrap.Alert.getOrCreateInstance(el).close(); }, 4000);
    });

    // ---- review actions ----
    // One modal serves all three so the reviewer always gets a chance to add a
    // note. On a rejection the note is compulsory: an employee cannot fix what
    // they were not told.
    const modalEl = document.getElementById('msReviewModal');
    if (!modalEl) return;

    const modal   = bootstrap.Modal.getOrCreateInstance(modalEl);
    const form    = document.getElementById('msReviewForm');
    const remarks = document.getElementById('msRemarks');
    const label   = document.getElementById('msRemarksLabel');
    const hint    = document.getElementById('msRemarksHint');
    const title   = document.getElementById('msReviewTitle');
    const submit  = document.getElementById('msReviewSubmit');
    const actIn   = document.getElementById('msAction');
    const BASE    = @json(url('/mind-speak'));

    const COPY = {
      approve:     { verb: 'Approve',     icon: 'bx-check',                 btn: 'btn-success',
                     label: 'Remarks for the employee (optional)',
                     hint:  'Anything you add is shown to the employee alongside the approval.' },
      acknowledge: { verb: 'Acknowledge', icon: 'bx-message-rounded-check', btn: 'btn-primary',
                     label: 'Reply to the employee (optional)',
                     hint:  'Your reply appears on their Mind Speak page.' },
      reject:      { verb: 'Reject',      icon: 'bx-x',                     btn: 'btn-danger',
                     label: 'Reason for rejection (required)',
                     hint:  'The employee sees this and can correct the entry and resubmit.' },
    };

    // Delegated, so buttons inside a detail modal are covered by the same
    // handler as the ones in the table.
    document.addEventListener('click', function (e) {
      const btn = e.target.closest('.ms-review-btn');
      if (!btn) return;

      const action = btn.dataset.action;
      const copy   = COPY[action];
      if (!copy) return;

      form.action       = BASE + '/' + btn.dataset.id + '/review';
      actIn.value       = action;
      title.textContent = copy.verb + ' — ' + btn.dataset.category;
      label.textContent = copy.label;
      hint.textContent  = copy.hint;
      remarks.required  = (action === 'reject');
      remarks.value     = '';
      remarks.placeholder = action === 'reject'
        ? 'What needs to change before this can be accepted?'
        : 'What should the employee know?';
      submit.className   = 'btn rounded-pill px-4 fw-semibold ' + copy.btn;
      submit.innerHTML   = '<i class="bx ' + copy.icon + ' me-1"></i>' + copy.verb;

      // When the click came from a detail modal, close that one first —
      // two stacked modals fight over the backdrop.
      const parent = btn.dataset.fromModal ? document.getElementById(btn.dataset.fromModal) : null;
      if (parent) {
        parent.addEventListener('hidden.bs.modal', function open() {
          parent.removeEventListener('hidden.bs.modal', open);
          modal.show();
        });
        bootstrap.Modal.getOrCreateInstance(parent).hide();
      } else {
        modal.show();
      }
    });

    // A rejection with only spaces in the box is refused by the server; say so
    // here rather than making the reviewer discover it after a round trip.
    form.addEventListener('submit', function (e) {
      if (actIn.value === 'reject' && remarks.value.trim() === '') {
        e.preventDefault();
        remarks.classList.add('is-invalid');
        remarks.focus();
      }
    });
    remarks.addEventListener('input', function () { remarks.classList.remove('is-invalid'); });

    modalEl.addEventListener('shown.bs.modal', function () { remarks.focus(); });
  });
</script>
@endsection
