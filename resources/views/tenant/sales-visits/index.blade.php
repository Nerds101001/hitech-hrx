@extends('layouts.layoutMaster')

@section('title', 'Sales Field Visits')

@section('vendor-style')
<link rel="stylesheet" href="{{ asset('vendor/libs/select2/select2.css') }}">
<link rel="stylesheet" href="{{ asset('vendor/libs/flatpickr/flatpickr.css') }}">
@endsection

@section('page-style')
<style>
  /* ===================== Hero Header ===================== */
  .sv-hero {
    background: linear-gradient(135deg, #0d7377 0%, #14a085 40%, #0a9396 70%, #00b4d8 100%);
    border-radius: 18px;
    padding: 2.5rem 2.8rem;
    margin-bottom: 2rem;
    position: relative;
    overflow: hidden;
    box-shadow: 0 8px 32px rgba(13, 115, 119, 0.30);
  }
  .sv-hero::before {
    content: '';
    position: absolute;
    top: -60px; right: -60px;
    width: 260px; height: 260px;
    background: rgba(255,255,255,0.07);
    border-radius: 50%;
  }
  .sv-hero::after {
    content: '';
    position: absolute;
    bottom: -80px; left: -40px;
    width: 200px; height: 200px;
    background: rgba(255,255,255,0.05);
    border-radius: 50%;
  }
  .sv-hero-title {
    font-size: 1.85rem;
    font-weight: 800;
    color: #fff;
    letter-spacing: -0.5px;
    margin-bottom: 0.3rem;
  }
  .sv-hero-subtitle {
    color: rgba(255,255,255,0.80);
    font-size: 0.97rem;
    margin-bottom: 0;
  }
  .sv-hero .btn-new-visit {
    background: #fff;
    color: #0d7377;
    border: none;
    font-weight: 700;
    border-radius: 10px;
    padding: 0.65rem 1.4rem;
    font-size: 0.92rem;
    box-shadow: 0 4px 18px rgba(0,0,0,0.12);
    transition: all 0.22s ease;
  }
  .sv-hero .btn-new-visit:hover {
    background: #0d7377;
    color: #fff;
    box-shadow: 0 6px 22px rgba(13,115,119,0.35);
    transform: translateY(-2px);
  }

  /* ===================== KPI Cards ===================== */
  .sv-stat-card {
    border-radius: 16px;
    padding: 1.5rem 1.6rem;
    position: relative;
    overflow: hidden;
    border: none;
    box-shadow: 0 4px 24px rgba(0,0,0,0.08);
    transition: transform 0.22s ease, box-shadow 0.22s ease;
  }
  .sv-stat-card:hover {
    transform: translateY(-4px);
    box-shadow: 0 10px 32px rgba(0,0,0,0.14);
  }
  .sv-stat-card .stat-icon {
    width: 52px; height: 52px;
    border-radius: 14px;
    display: flex; align-items: center; justify-content: center;
    font-size: 1.5rem;
    margin-bottom: 1rem;
  }
  .sv-stat-card .stat-value {
    font-size: 2.1rem;
    font-weight: 800;
    line-height: 1;
    margin-bottom: 0.25rem;
  }
  .sv-stat-card .stat-label {
    font-size: 0.83rem;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    opacity: 0.72;
  }
  .stat-total    { background: linear-gradient(135deg, #0d7377, #14a085); color:#fff; }
  .stat-today    { background: linear-gradient(135deg, #3a0ca3, #7209b7); color:#fff; }
  .stat-pending  { background: linear-gradient(135deg, #f77f00, #fcbf49); color:#fff; }
  .stat-done     { background: linear-gradient(135deg, #2dc653, #38b000); color:#fff; }
  .stat-total  .stat-icon { background: rgba(255,255,255,0.18); }
  .stat-today  .stat-icon { background: rgba(255,255,255,0.18); }
  .stat-pending .stat-icon { background: rgba(255,255,255,0.18); }
  .stat-done   .stat-icon { background: rgba(255,255,255,0.18); }

  /* ===================== Filter Bar ===================== */
  .sv-filter-bar {
    background: #fff;
    border-radius: 14px;
    padding: 1.2rem 1.6rem;
    box-shadow: 0 2px 16px rgba(0,0,0,0.07);
    margin-bottom: 1.5rem;
  }
  .sv-filter-bar .form-label {
    font-size: 0.78rem;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    color: #5a5f71;
    margin-bottom: 0.3rem;
  }
  .sv-filter-bar .form-select,
  .sv-filter-bar .form-control {
    border-radius: 9px;
    font-size: 0.88rem;
    border-color: #e3e6ef;
    height: 40px;
  }

  /* ===================== Table ===================== */
  .sv-table-card {
    border-radius: 16px;
    overflow: hidden;
    box-shadow: 0 4px 24px rgba(0,0,0,0.07);
    border: none;
  }
  .sv-table-card .card-header {
    background: linear-gradient(90deg, #f8fffe, #edf9f9);
    border-bottom: 2px solid #c7eaea;
    padding: 1rem 1.6rem;
    font-weight: 700;
    color: #0d7377;
    font-size: 0.95rem;
  }
  .sv-table thead th {
    background: #f3fffe;
    color: #0d7377;
    font-size: 0.78rem;
    font-weight: 800;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    border-bottom: 2px solid #c7eaea;
    padding: 0.9rem 1rem;
    white-space: nowrap;
  }
  .sv-table tbody tr {
    transition: background 0.15s ease;
  }
  .sv-table tbody tr:hover {
    background: #f0fffe;
  }
  .sv-table td {
    padding: 0.85rem 1rem;
    vertical-align: middle;
    font-size: 0.88rem;
    color: #3d3d3d;
    border-bottom: 1px solid #f0f2f5;
  }
  .sv-client-name {
    font-weight: 700;
    color: #1a1a2e;
    display: block;
    font-size: 0.9rem;
  }
  .sv-client-city {
    font-size: 0.78rem;
    color: #8a90a2;
  }
  .sv-badge {
    display: inline-block;
    padding: 0.3em 0.8em;
    border-radius: 20px;
    font-size: 0.75rem;
    font-weight: 700;
    letter-spacing: 0.3px;
  }
  .badge-visit-type  { background:#e8f4fd; color:#0077b6; }
  .badge-pending     { background:#fff3cd; color:#856404; }
  .badge-confirmed   { background:#cff4fc; color:#055160; }
  .badge-completed   { background:#d1e7dd; color:#0a3622; }
  .badge-cancelled   { background:#f8d7da; color:#58151c; }

  .btn-action-view {
    background: linear-gradient(135deg, #0d7377, #14a085);
    color: #fff;
    border: none;
    border-radius: 8px;
    padding: 0.35rem 0.75rem;
    font-size: 0.8rem;
    transition: all 0.2s ease;
  }
  .btn-action-view:hover {
    opacity: 0.88;
    color: #fff;
    transform: translateY(-1px);
  }
  .btn-action-cancel {
    background: #fff2f2;
    color: #d63031;
    border: 1px solid #f5c6c6;
    border-radius: 8px;
    padding: 0.35rem 0.75rem;
    font-size: 0.8rem;
    transition: all 0.2s ease;
  }
  .btn-action-cancel:hover {
    background: #d63031;
    color: #fff;
  }

  /* ===================== Empty State ===================== */
  .sv-empty {
    padding: 4rem 2rem;
    text-align: center;
    color: #aab0be;
  }
  .sv-empty .empty-icon {
    font-size: 3.5rem;
    margin-bottom: 1rem;
    opacity: 0.5;
  }
  .sv-empty p {
    font-size: 0.95rem;
    font-weight: 500;
    margin-bottom: 0;
  }

  /* ===================== Alerts ===================== */
  .sv-alert {
    border-radius: 12px;
    border-left: 5px solid;
    padding: 0.9rem 1.2rem;
    margin-bottom: 1.2rem;
    font-size: 0.9rem;
    font-weight: 500;
  }
</style>
@endsection

@section('content')
<div class="container-xxl flex-grow-1 container-p-y">

  {{-- Flash Messages --}}
  @if(session('success'))
    <div class="sv-alert alert alert-success alert-dismissible fade show" role="alert">
      <i class="bx bx-check-circle me-2"></i> {{ session('success') }}
      <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
  @endif
  @if(session('error'))
    <div class="sv-alert alert alert-danger alert-dismissible fade show" role="alert">
      <i class="bx bx-error-circle me-2"></i> {{ session('error') }}
      <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
  @endif

  {{-- ===== Hero Header ===== --}}
  <div class="sv-hero mb-4">
    <div class="d-flex justify-content-between align-items-center" style="position:relative; z-index:2;">
      <div>
        <h1 class="sv-hero-title">📍 Sales Field Visits & Trials</h1>
        <p class="sv-hero-subtitle">Track, schedule and manage all client visits, product trials and service calls in one place.</p>
      </div>
      <div>
        <a href="{{ route('sales-visits.create') }}" class="btn btn-new-visit">
          <i class="bx bx-plus me-1"></i> Book New Visit
        </a>
      </div>
    </div>
  </div>

  {{-- ===== KPI Stat Cards ===== --}}
  <div class="row g-3 mb-4">
    <div class="col-6 col-xl-3">
      <div class="sv-stat-card stat-total">
        <div class="stat-icon">📊</div>
        <div class="stat-value">{{ $stats['total'] ?? 0 }}</div>
        <div class="stat-label">Total Visits</div>
      </div>
    </div>
    <div class="col-6 col-xl-3">
      <div class="sv-stat-card stat-today">
        <div class="stat-icon">📅</div>
        <div class="stat-value">{{ $stats['today'] ?? 0 }}</div>
        <div class="stat-label">Today's Visits</div>
      </div>
    </div>
    <div class="col-6 col-xl-3">
      <div class="sv-stat-card stat-pending">
        <div class="stat-icon">⏳</div>
        <div class="stat-value">{{ $stats['pending'] ?? 0 }}</div>
        <div class="stat-label">Pending</div>
      </div>
    </div>
    <div class="col-6 col-xl-3">
      <div class="sv-stat-card stat-done">
        <div class="stat-icon">✅</div>
        <div class="stat-value">{{ $stats['completed'] ?? 0 }}</div>
        <div class="stat-label">Completed</div>
      </div>
    </div>
  </div>

  {{-- ===== Filter Bar ===== --}}
  <div class="sv-filter-bar">
    <form method="GET" action="{{ route('sales-visits.index') }}" id="filterForm">
      <div class="row g-3 align-items-end">
        <div class="col-sm-6 col-lg-2">
          <label class="form-label">Status</label>
          <select name="status" class="form-select">
            <option value="">All Status</option>
            <option value="pending"   {{ request('status') == 'pending'   ? 'selected' : '' }}>Pending</option>
            <option value="confirmed" {{ request('status') == 'confirmed' ? 'selected' : '' }}>Confirmed</option>
            <option value="completed" {{ request('status') == 'completed' ? 'selected' : '' }}>Completed</option>
            <option value="cancelled" {{ request('status') == 'cancelled' ? 'selected' : '' }}>Cancelled</option>
          </select>
        </div>
        <div class="col-sm-6 col-lg-2">
          <label class="form-label">Visit Type</label>
          <select name="visit_type" class="form-select">
            <option value="">All Types</option>
            <option value="client_visit"      {{ request('visit_type') == 'client_visit'      ? 'selected' : '' }}>Client Visit</option>
            <option value="product_trial"     {{ request('visit_type') == 'product_trial'     ? 'selected' : '' }}>Product Trial</option>
            <option value="order_collection"  {{ request('visit_type') == 'order_collection'  ? 'selected' : '' }}>Order Collection</option>
            <option value="service_call"      {{ request('visit_type') == 'service_call'      ? 'selected' : '' }}>Service Call</option>
          </select>
        </div>
        <div class="col-sm-6 col-lg-2">
          <label class="form-label">Verification</label>
          <select name="verification_status" class="form-select">
            <option value="">All</option>
            <option value="pending"  {{ request('verification_status') == 'pending'  ? 'selected' : '' }}>Unverified</option>
            <option value="approved" {{ request('verification_status') == 'approved' ? 'selected' : '' }}>Approved</option>
            <option value="rejected" {{ request('verification_status') == 'rejected' ? 'selected' : '' }}>Rejected</option>
          </select>
        </div>
        <div class="col-sm-6 col-lg-2">
          <label class="form-label">From Date</label>
          <input type="date" name="date_from" class="form-control" value="{{ request('date_from') }}">
        </div>
        <div class="col-sm-6 col-lg-2">
          <label class="form-label">To Date</label>
          <input type="date" name="date_to" class="form-control" value="{{ request('date_to') }}">
        </div>
        <div class="col-sm-6 col-lg-2">
          <label class="form-label">Salesperson</label>
          <select name="salesperson_id" class="form-select">
            <option value="">All Salespersons</option>
            @foreach($salespersons as $sp)
              <option value="{{ $sp->id }}" {{ request('salesperson_id') == $sp->id ? 'selected' : '' }}>
                {{ $sp->name }}
              </option>
            @endforeach
          </select>
        </div>
        <div class="col-sm-6 col-lg-2">
          <label class="form-label">&nbsp;</label>
          <div class="d-flex gap-2">
            <button type="submit" class="btn btn-sm w-100" style="background:#0d7377;color:#fff;border-radius:9px;font-weight:700;">
              <i class="bx bx-search me-1"></i> Search
            </button>
            <a href="{{ route('sales-visits.index') }}" class="btn btn-sm btn-outline-secondary" style="border-radius:9px;" title="Reset">
              <i class="bx bx-reset"></i>
            </a>
          </div>
        </div>
      </div>
    </form>
  </div>

  {{-- ===== Visits Table ===== --}}
  <div class="card sv-table-card">
    <div class="card-header d-flex justify-content-between align-items-center">
      <span><i class="bx bx-list-ul me-2"></i>All Visits
        <span class="badge ms-2" style="background:#0d7377;font-size:0.78rem;">{{ $visits->total() }}</span>
      </span>
      <small class="text-muted">Showing {{ $visits->firstItem() ?? 0 }}–{{ $visits->lastItem() ?? 0 }} of {{ $visits->total() }}</small>
    </div>
    <div class="card-body p-0">
      @if($visits->count() > 0)
        <div class="table-responsive">
          <table class="table sv-table mb-0">
            <thead>
              <tr>
                <th>#</th>
                <th>Client</th>
                <th>Visit Type</th>
                <th>Salesperson</th>
                <th>CC Agent</th>
                <th>Scheduled</th>
                <th>Status</th>
                <th class="text-center">Actions</th>
              </tr>
            </thead>
            <tbody>
              @foreach($visits as $i => $visit)
              <tr>
                <td class="text-muted" style="font-size:0.8rem;">{{ $visits->firstItem() + $i }}</td>
                <td>
                  <span class="sv-client-name">{{ $visit->client->name ?? '—' }}</span>
                  <span class="sv-client-city"><i class="bx bx-map-pin" style="font-size:0.75rem;"></i> {{ $visit->client->city ?? '' }}</span>
                </td>
                <td>
                  @php
                    $typeLabels = [
                      'client_visit'     => ['label'=>'Client Visit',     'icon'=>'🤝'],
                      'product_trial'    => ['label'=>'Product Trial',    'icon'=>'🧪'],
                      'order_collection' => ['label'=>'Order Collection', 'icon'=>'📦'],
                      'service_call'     => ['label'=>'Service Call',     'icon'=>'🔧'],
                    ];
                    $typeInfo = $typeLabels[$visit->visit_type] ?? ['label'=>ucfirst(str_replace('_',' ',$visit->visit_type)), 'icon'=>'📋'];
                  @endphp
                  <span class="sv-badge badge-visit-type">{{ $typeInfo['icon'] }} {{ $typeInfo['label'] }}</span>
                </td>
                <td>
                  <span style="font-weight:600;">{{ $visit->salesperson->name ?? '—' }}</span>
                </td>
                <td>
                  <span style="color:#5a5f71;">{{ $visit->ccAgent->name ?? '—' }}</span>
                </td>
                <td>
                  <span style="font-weight:600;">{{ \Carbon\Carbon::parse($visit->scheduled_at)->format('d M Y') }}</span><br>
                  <small class="text-muted">{{ \Carbon\Carbon::parse($visit->scheduled_at)->format('h:i A') }}</small>
                </td>
                <td>
                  @switch($visit->status)
                    @case('pending')
                      <span class="sv-badge badge-pending">⏳ Pending</span>
                      @break
                    @case('confirmed')
                      <span class="sv-badge badge-confirmed">✔ Confirmed</span>
                      @break
                    @case('completed')
                      <span class="sv-badge badge-completed">✅ Completed</span>
                      @if($visit->verification_status === 'approved')
                        <span class="sv-badge mt-1" style="background:#d1fae5;color:#065f46;"><i class="ti ti-shield-check"></i> Approved</span>
                      @elseif($visit->verification_status === 'rejected')
                        <span class="sv-badge mt-1" style="background:#fee2e2;color:#991b1b;"><i class="ti ti-shield-x"></i> Rejected</span>
                      @else
                        <span class="sv-badge mt-1" style="background:#fef3c7;color:#92400e;"><i class="ti ti-shield"></i> Unverified</span>
                      @endif
                      @break
                    @case('cancelled')
                      <span class="sv-badge badge-cancelled">✖ Cancelled</span>
                      @break
                    @default
                      <span class="sv-badge" style="background:#f0f0f0;color:#555;">{{ ucfirst($visit->status) }}</span>
                  @endswitch
                </td>
                <td class="text-center">
                  <div class="d-flex gap-2 justify-content-center">
                    <a href="{{ route('sales-visits.show', $visit->id) }}" class="btn btn-action-view" title="View Details">
                      <i class="bx bx-show"></i>
                    </a>
                    @if($visit->status === 'pending')
                    <form method="POST" action="{{ route('sales-visits.cancel', $visit->id) }}"
                          onsubmit="return confirm('Cancel this visit?');">
                      @csrf
                      @method('PATCH')
                      <button type="submit" class="btn btn-action-cancel" title="Cancel Visit">
                        <i class="bx bx-x"></i> Cancel
                      </button>
                    </form>
                    @endif
                  </div>
                </td>
              </tr>
              @endforeach
            </tbody>
          </table>
        </div>

        {{-- Pagination --}}
        <div class="d-flex justify-content-center align-items-center p-3">
          {{ $visits->withQueryString()->links() }}
        </div>

      @else
        {{-- Empty State --}}
        <div class="sv-empty">
          <div class="empty-icon">🗂️</div>
          <p>No visits found for the selected filters.</p>
          <a href="{{ route('sales-visits.index') }}" class="btn btn-sm mt-2" style="background:#0d7377;color:#fff;border-radius:9px;">
            Clear Filters
          </a>
        </div>
      @endif
    </div>
  </div>

</div>
@endsection

@section('vendor-script')
<script src="{{ asset('vendor/libs/select2/select2.js') }}"></script>
@endsection

@section('page-script')
<script>
  // Auto-dismiss alerts after 4s
  document.querySelectorAll('.sv-alert').forEach(el => {
    setTimeout(() => {
      const bsAlert = new bootstrap.Alert(el);
      bsAlert.close();
    }, 4000);
  });
</script>
@endsection
