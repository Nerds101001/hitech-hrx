@extends('layouts.layoutMaster')

@section('title', 'Sales Field Visits')

@section('vendor-style')
<link rel="stylesheet" href="{{ asset('vendor/libs/select2/select2.css') }}">
<link rel="stylesheet" href="{{ asset('vendor/libs/flatpickr/flatpickr.css') }}">
@vite([
  'resources/assets/vendor/scss/pages/hitech-portal.scss'
])
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
  .sv-client-name {
    font-weight: 700;
    color: #1A1A2E;
    display: block;
    font-size: 0.9rem;
  }
  .sv-client-city {
    font-size: 0.78rem;
    color: #8A90A2;
  }
  .badge-visit-type  { background:#E8F4FD; color:#0077B6; border-radius: 20px; padding: 0.3em 0.8em; font-size: 0.75rem; font-weight: 700; }

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
<div class="animate__animated animate__fadeIn px-4">

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
  <div class="row g-6 mb-6">
    <div class="col-12 col-sm-6 col-xl-3">
      <div class="hitech-stat-card dashboard-variant card-teal h-100">
        <div class="stat-card-header">
          <div class="stat-icon-wrap icon-teal"><i class="bx bx-bar-chart-alt-2"></i></div>
        </div>
        <div>
          <h3 class="stat-value mb-1">{{ $stats['total'] ?? 0 }}</h3>
          <small class="stat-label">Total Visits</small>
        </div>
      </div>
    </div>
    <div class="col-12 col-sm-6 col-xl-3">
      <div class="hitech-stat-card dashboard-variant card-blue h-100">
        <div class="stat-card-header">
          <div class="stat-icon-wrap icon-blue"><i class="bx bx-calendar"></i></div>
        </div>
        <div>
          <h3 class="stat-value mb-1">{{ $stats['today'] ?? 0 }}</h3>
          <small class="stat-label">Today's Visits</small>
        </div>
      </div>
    </div>
    <div class="col-12 col-sm-6 col-xl-3">
      <div class="hitech-stat-card dashboard-variant card-amber h-100">
        <div class="stat-card-header">
          <div class="stat-icon-wrap icon-amber"><i class="bx bx-time-five"></i></div>
        </div>
        <div>
          <h3 class="stat-value mb-1">{{ $stats['pending'] ?? 0 }}</h3>
          <small class="stat-label">Pending</small>
        </div>
      </div>
    </div>
    <div class="col-12 col-sm-6 col-xl-3">
      <div class="hitech-stat-card dashboard-variant card-teal h-100">
        <div class="stat-card-header">
          <div class="stat-icon-wrap icon-teal"><i class="bx bx-check-double"></i></div>
        </div>
        <div>
          <h3 class="stat-value mb-1">{{ $stats['completed'] ?? 0 }}</h3>
          <small class="stat-label">Completed</small>
        </div>
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
  <div class="hitech-card shadow-sm mb-6">
    <div class="hitech-card-header d-flex justify-content-between align-items-center">
      <h5 class="title mb-0 d-flex align-items-center gap-2" style="color: #005a5a;">
        <i class="bx bx-list-ul fs-4" style="color: #0d9488;"></i>
        <span>All Visits</span>
        <span class="badge rounded-pill fw-bold" style="background: rgba(0, 90, 90, 0.15); color: #005a5a; font-size: 0.85rem;">{{ $visits->total() }}</span>
      </h5>
      <small class="text-muted">Showing {{ $visits->firstItem() ?? 0 }}–{{ $visits->lastItem() ?? 0 }} of {{ $visits->total() }}</small>
    </div>
    <div class="card-body p-0">
      @if($visits->count() > 0)
        <div class="table-responsive">
          <table class="table mb-0 table-hover align-middle">
            <thead>
              <tr>
                <th class="ps-4">#</th>
                <th>Client</th>
                <th>Visit Type</th>
                <th>Salesperson</th>
                <th>CC Agent</th>
                <th>Scheduled</th>
                <th>Status</th>
                <th>Feedback</th>
                <th class="text-center pe-4">Actions</th>
              </tr>
            </thead>
            <tbody>
              @foreach($visits as $i => $visit)
              <tr>
                <td class="text-muted ps-4" style="font-size:0.8rem;">{{ $visits->firstItem() + $i }}</td>
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
                  <span class="badge badge-visit-type">{{ $typeInfo['icon'] }} {{ $typeInfo['label'] }}</span>
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
                  <div class="d-flex flex-column align-items-start gap-1">
                    @switch($visit->status)
                      @case('pending')
                        <span class="badge bg-label-warning px-3 py-1.5 fw-bold"><i class="bx bx-time-five me-1"></i> Pending</span>
                        @break
                      @case('confirmed')
                        <span class="badge bg-label-info px-3 py-1.5 fw-bold"><i class="bx bx-check me-1"></i> Confirmed</span>
                        @break
                      @case('completed')
                        <span class="badge bg-label-success px-3 py-1.5 fw-bold"><i class="bx bx-check-double me-1"></i> Completed</span>
                        @if($visit->verification_status === 'approved')
                          <span class="badge bg-label-success px-3 py-1.5 fw-bold"><i class="bx bx-shield-quarter me-1"></i> Approved</span>
                        @elseif($visit->verification_status === 'rejected')
                          <span class="badge bg-label-danger px-3 py-1.5 fw-bold"><i class="bx bx-shield-x me-1"></i> Rejected</span>
                        @else
                          <span class="badge bg-label-warning px-3 py-1.5 fw-bold"><i class="bx bx-shield-minus me-1"></i> Unverified</span>
                        @endif
                        @break
                      @case('cancelled')
                        <span class="badge bg-label-danger px-3 py-1.5 fw-bold"><i class="bx bx-x me-1"></i> Cancelled</span>
                        @break
                      @default
                        <span class="badge bg-label-secondary px-3 py-1.5 fw-bold">{{ ucfirst($visit->status) }}</span>
                    @endswitch
                  </div>
                </td>
                <td>
                  @if($visit->rating)
                    <div class="d-flex align-items-center gap-1">
                      <span class="text-warning">
                        @for($i=1; $i<=5; $i++)
                          <i class="bx {{ $i <= $visit->rating ? 'bxs-star' : 'bx-star' }}" style="font-size:0.95rem;"></i>
                        @endfor
                      </span>
                      @if($visit->rating_comment)
                        <button type="button" class="btn btn-icon btn-sm rounded-pill text-muted p-0 m-0" style="width:20px;height:20px;" data-bs-toggle="tooltip" data-bs-placement="top" title="{{ $visit->rating_comment }}">
                          <i class="bx bx-message-dots" style="font-size:1.1rem; color:#0d9488;"></i>
                        </button>
                      @endif
                    </div>
                  @else
                    <span class="text-muted" style="font-size:0.75rem;">—</span>
                  @endif
                </td>
                <td class="text-center pe-4">
                  <div class="d-flex gap-2 justify-content-center align-items-center">
                    <a href="{{ route('sales-visits.show', $visit->id) }}" class="btn btn-sm btn-icon btn-label-teal rounded-pill" title="View Details">
                      <i class="bx bx-show fs-5"></i>
                    </a>
                    @if($visit->status === 'pending')
                    <form method="POST" action="{{ route('sales-visits.cancel', $visit->id) }}"
                          class="m-0"
                          onsubmit="return confirm('Cancel this visit?');">
                      @csrf
                      @method('PATCH')
                      <button type="submit" class="btn btn-sm btn-label-danger rounded-pill px-3" title="Cancel Visit">
                        <i class="bx bx-x me-1"></i> Cancel
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

  // Initialize tooltips
  var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'))
  var tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
    return new bootstrap.Tooltip(tooltipTriggerEl)
  });
</script>
@endsection
