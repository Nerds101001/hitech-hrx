@extends('layouts.layoutMaster')

@section('title', 'Mind Speak Dashboard')

@section('vendor-style')
  @vite([
    'resources/assets/vendor/scss/pages/hitech-portal.scss'
  ])
@endsection

@section('page-style')
<style>
  /* ===================== Custom KPI Purple Card for Anonymous ===================== */
  .card-purple::after {
    background: linear-gradient(90deg, #8b5cf6, #c084fc);
  }
  .icon-purple {
    background: rgba(139, 92, 246, 0.1);
    color: #7c3aed;
  }

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
    border-bottom: 1px solid #f1f5f9;
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
    border-color: #e5e7eb;
    height: 40px;
  }
  .filter-bar .btn-filter {
    background: #0d7377;
    color: #fff;
    border: none;
    border-radius: 9px;
    font-weight: 700;
    height: 40px;
    font-size: 0.88rem;
    transition: background 0.2s;
  }
  .filter-bar .btn-filter:hover {
    background: #0a585b;
  }
  .filter-bar .btn-export {
    background: #10b981;
    color: #fff;
    border: none;
    border-radius: 9px;
    font-weight: 700;
    height: 40px;
    font-size: 0.88rem;
    transition: background 0.2s;
  }
  .filter-bar .btn-export:hover {
    background: #059669;
  }

  /* ===================== Submissions Table ===================== */
  .ms-table thead th {
    background: #f8fafc;
    color: #1e293b;
    font-size: 0.78rem;
    font-weight: 800;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    border-bottom: 2px solid #f1f5f9;
    padding: 1rem;
  }
  .ms-table tbody tr {
    transition: background 0.15s ease;
  }
  .ms-table tbody tr:hover {
    background: #f8fafc;
  }
  .ms-table td {
    padding: 1rem;
    vertical-align: middle;
    font-size: 0.88rem;
    color: #374151;
    border-bottom: 1px solid #f1f5f9;
  }
  
  .ms-badge {
    display: inline-block;
    padding: 0.35em 0.85em;
    border-radius: 20px;
    font-size: 0.75rem;
    font-weight: 700;
    letter-spacing: 0.3px;
  }
  .badge-suggestion { background: #e0e7ff; color: #3730a3; }
  .badge-complaint  { background: #fee2e2; color: #991b1b; }
  .badge-improvement { background: #dcfce7; color: #166534; }
  .badge-other       { background: #f3f4f6; color: #374151; }

  .emp-code-badge {
    background: #f1f5f9;
    color: #334155;
    font-weight: 700;
    padding: 0.25rem 0.6rem;
    border-radius: 6px;
    font-size: 0.8rem;
    border: 1px solid #e2e8f0;
  }

  .dept-list-item {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 0.75rem 1rem;
    border-bottom: 1px solid #f1f5f9;
  }
  .dept-list-item:last-child {
    border-bottom: none;
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
    <div class="hitech-page-hero-text">
      <h4 class="greeting">Mind Speak Dashboard</h4>
      <p class="sub-text">Review suggestions, feedback, complaints, and organizational improvement plans submitted by staff members.</p>
    </div>
  </div>

  {{-- KPI Stat Cards --}}
  <div class="row g-6 mb-6">
    <div class="col-12 col-sm-6 col-md-4 col-lg">
      <div class="hitech-stat-card dashboard-variant card-blue h-100">
        <div class="stat-card-header">
          <div class="stat-icon-wrap icon-blue"><i class="bx bx-brain"></i></div>
        </div>
        <div>
          <h3 class="stat-value mb-1">{{ number_format($stats['total']) }}</h3>
          <small class="stat-label">Total Speaks</small>
        </div>
      </div>
    </div>
    <div class="col-12 col-sm-6 col-md-4 col-lg">
      <div class="hitech-stat-card dashboard-variant card-teal h-100">
        <div class="stat-card-header">
          <div class="stat-icon-wrap icon-teal"><i class="bx bx-bulb"></i></div>
        </div>
        <div>
          <h3 class="stat-value mb-1">{{ number_format($stats['suggestion']) }}</h3>
          <small class="stat-label">Suggestions</small>
        </div>
      </div>
    </div>
    <div class="col-12 col-sm-6 col-md-4 col-lg">
      <div class="hitech-stat-card dashboard-variant card-amber h-100">
        <div class="stat-card-header">
          <div class="stat-icon-wrap icon-amber"><i class="bx bx-rocket"></i></div>
        </div>
        <div>
          <h3 class="stat-value mb-1">{{ number_format($stats['improvement']) }}</h3>
          <small class="stat-label">Improvements</small>
        </div>
      </div>
    </div>
    <div class="col-12 col-sm-6 col-md-4 col-lg">
      <div class="hitech-stat-card dashboard-variant card-red h-100">
        <div class="stat-card-header">
          <div class="stat-icon-wrap icon-red"><i class="bx bx-error-circle"></i></div>
        </div>
        <div>
          <h3 class="stat-value mb-1">{{ number_format($stats['complaint']) }}</h3>
          <small class="stat-label">Complaints</small>
        </div>
      </div>
    </div>
    <div class="col-12 col-sm-6 col-md-4 col-lg">
      <div class="hitech-stat-card dashboard-variant card-purple h-100">
        <div class="stat-card-header">
          <div class="stat-icon-wrap icon-purple"><i class="bx bx-hide"></i></div>
        </div>
        <div>
          <h3 class="stat-value mb-1">{{ number_format($stats['anonymous']) }}</h3>
          <small class="stat-label">Anonymous</small>
        </div>
      </div>
    </div>
  </div>

  {{-- Filter & Sort Form --}}
  <div class="filter-bar">
    <form method="GET" action="{{ route('mind-speak.index') }}" id="filterForm">
      <div class="row g-3 align-items-end">
        <div class="col-12 col-sm-6 col-md-2">
          <label class="form-label">Category</label>
          <select name="category" class="form-select">
            <option value="">All Categories</option>
            <option value="Suggestion" {{ request('category') == 'Suggestion' ? 'selected' : '' }}>Suggestion</option>
            <option value="Complaint" {{ request('category') == 'Complaint' ? 'selected' : '' }}>Complaint</option>
            <option value="Improvement" {{ request('category') == 'Improvement' ? 'selected' : '' }}>Improvement</option>
            <option value="Feedback" {{ request('category') == 'Feedback' ? 'selected' : '' }}>Feedback</option>
            <option value="Other" {{ request('category') == 'Other' ? 'selected' : '' }}>Other</option>
          </select>
        </div>

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
          <div class="d-flex gap-2">
            <button type="submit" class="btn btn-filter w-100 fw-bold">
              <i class="bx bx-filter-alt me-1"></i> Filter
            </button>
            <a href="{{ route('mind-speak.index') }}" class="btn btn-outline-secondary d-flex align-items-center justify-content-center" style="border-radius: 9px; height: 40px; width: 40px; min-width: 40px; padding: 0; flex-shrink: 0;" title="Reset Filters">
              <i class="bx bx-reset fs-5"></i>
            </a>
            <button type="button" onclick="exportData()" class="btn btn-export d-flex align-items-center justify-content-center" style="border-radius: 9px; height: 40px; width: 40px; min-width: 40px; padding: 0; flex-shrink: 0;" title="Export to CSV">
              <i class="bx bx-download fs-5"></i>
            </button>
          </div>
        </div>
      </div>
    </form>
  </div>

  {{-- Breakdowns & Analytics (Month-wise and Department-wise) --}}
  <div class="row g-4 mb-4">
    {{-- Month-wise Card --}}
    <div class="col-12 col-lg-7">
      <div class="card ms-card h-100">
        <div class="ms-card-header">
          <h5 class="ms-card-title"><i class="bx bx-chart me-1 text-primary"></i> Monthly Submissions Trends (Current Year)</h5>
        </div>
        <div class="card-body p-4">
          <canvas id="monthlyTrendChart" style="max-height: 280px; width: 100%;"></canvas>
        </div>
      </div>
    </div>

    {{-- Department-wise Card --}}
    <div class="col-12 col-lg-5">
      <div class="card ms-card h-100">
        <div class="ms-card-header">
          <h5 class="ms-card-title"><i class="bx bx-sitemap me-1 text-primary"></i> Department Submissions Breakdown</h5>
        </div>
        <div class="card-body p-0" style="max-height: 320px; overflow-y: auto;">
          @if($deptStats->count() > 0)
            <div class="dept-list">
              @foreach($deptStats as $deptStat)
                <div class="dept-list-item">
                  <div class="d-flex align-items-center">
                    <span style="width: 8px; height: 8px; border-radius: 50%; background-color: #0d9488; margin-right: 12px; display: inline-block;"></span>
                    <span class="fw-semibold text-dark">{{ $deptStat->department_name ?? 'Unassigned / Other' }}</span>
                  </div>
                  <span class="badge rounded-pill fw-bold" style="background: rgba(0, 90, 90, 0.15); color: #005a5a; font-size: 0.8rem;">
                    {{ $deptStat->total }}
                  </span>
                </div>
              @endforeach
            </div>
          @else
            <div class="text-center py-6 text-muted">
              <i class="bx bx-folder fs-1 text-light d-block mb-2" style="font-size: 3rem !important;"></i>
              No department data available.
            </div>
          @endif
        </div>
      </div>
    </div>
  </div>

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
                <th>Category</th>
                <th>Content / Idea</th>
                <th>Employee ID</th>
                <th>Submitter Info</th>
                <th>Department</th>
                <th>Submitted Date</th>
              </tr>
            </thead>
            <tbody>
              @foreach($submissions as $sub)
                <tr>
                  <td>
                    @php
                      $badgeClass = match($sub->category) {
                        'Suggestion' => 'badge-suggestion',
                        'Complaint' => 'badge-complaint',
                        'Improvement' => 'badge-improvement',
                        default => 'badge-other',
                      };
                    @endphp
                    <span class="ms-badge {{ $badgeClass }}">{{ $sub->category }}</span>
                  </td>
                  <td>
                    <div style="max-width: 320px; min-width: 180px; font-size: 0.85rem; line-height: 1.5; color: #374151;">
                      {{ \Illuminate\Support\Str::limit($sub->content, 140) }}
                      @if(strlen($sub->content) > 140)
                        <a href="javascript:void(0);" class="text-primary fw-bold ms-1" data-bs-toggle="modal" data-bs-target="#viewModalAdmin{{ $sub->id }}">Read Details</a>
                      @endif
                    </div>

                    {{-- Detail Modal --}}
                    <div class="modal fade" id="viewModalAdmin{{ $sub->id }}" tabindex="-1" aria-hidden="true">
                      <div class="modal-dialog modal-dialog-centered modal-lg">
                        <div class="modal-content" style="border-radius: 16px; border: none; box-shadow: 0 10px 30px rgba(0,0,0,0.15);">
                          <div class="modal-header bg-light" style="border-bottom: 1px solid #f1f5f9; border-top-left-radius: 16px; border-top-right-radius: 16px;">
                            <h5 class="modal-title fw-bold text-dark d-flex align-items-center">
                              <span class="ms-badge {{ $badgeClass }} me-3">{{ $sub->category }}</span> 
                              Mind Speak Submission Details
                            </h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                          </div>
                          <div class="modal-body p-4">
                            <div class="row g-4 mb-4">
                              <div class="col-md-6">
                                <span class="d-block text-uppercase text-muted fw-bold mb-1" style="font-size: 0.72rem; letter-spacing: 0.5px;">Employee ID</span>
                                <span class="emp-code-badge">{{ $sub->user->code }}</span>
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
                            <hr class="my-4" style="border-color: #f1f5f9;">
                            <span class="d-block text-uppercase text-muted fw-bold mb-2" style="font-size: 0.72rem; letter-spacing: 0.5px;">Submitted Content / Message</span>
                            <div class="p-3 bg-light rounded-3 text-dark" style="white-space: pre-line; line-height: 1.7; font-size: 0.95rem;">
                              {{ $sub->content }}
                            </div>
                          </div>
                          <div class="modal-footer bg-light" style="border-top: 1px solid #f1f5f9; border-bottom-left-radius: 16px; border-bottom-right-radius: 16px;">
                            <button type="button" class="btn btn-secondary rounded-pill px-4" data-bs-dismiss="modal">Close</button>
                          </div>
                        </div>
                      </div>
                    </div>

                  </td>
                  <td>
                    <span class="emp-code-badge">{{ $sub->user->code }}</span>
                  </td>
                  <td>
                    @if($sub->is_anonymous)
                      <span class="text-muted font-italic small"><i class="bx bx-hide align-middle"></i> [Anonymous]</span>
                    @else
                      <span class="fw-semibold text-dark">{{ $sub->user->full_name ?? $sub->user->name }}</span><br>
                      <small class="text-muted" style="font-size: 0.78rem;">{{ $sub->user->email }}</small>
                    @endif
                  </td>
                  <td>
                    @if($sub->is_anonymous)
                      <span class="text-muted small">—</span>
                    @else
                      <span class="text-dark fw-semibold" style="font-size: 0.85rem;">{{ $sub->user->department?->name ?? 'N/A' }}</span>
                    @endif
                  </td>
                  <td>
                    <span class="text-dark fw-semibold">{{ $sub->created_at->format('d M Y') }}</span><br>
                    <small class="text-muted" style="font-size: 0.75rem;">{{ $sub->created_at->format('h:i A') }}</small>
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

</div>
@endsection

@section('page-script')
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
  // Chart.js Configuration for Monthly Trend
  (function() {
    const ctx = document.getElementById('monthlyTrendChart');
    if (!ctx) return;

    const months = @json(array_keys($formattedMonths));
    const counts = @json(array_values($formattedMonths));

    new Chart(ctx, {
      type: 'bar',
      data: {
        labels: months,
        datasets: [{
          label: 'Submissions',
          data: counts,
          backgroundColor: 'rgba(13, 115, 119, 0.75)',
          borderColor: '#0d7377',
          borderWidth: 1,
          borderRadius: 6,
          borderSkipped: false,
          hoverBackgroundColor: '#0d7377'
        }]
      },
      options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
          legend: { display: false },
          tooltip: { bodyFont: { size: 13 } }
        },
        scales: {
          x: {
            grid: { display: false },
            ticks: { font: { size: 11 } }
          },
          y: {
            beginAtZero: true,
            ticks: { stepSize: 1 },
            grid: { color: 'rgba(0,0,0,.05)' }
          }
        }
      }
    });
  })();

  // CSV Export helper function with query parameters
  function exportData() {
    const filterForm = document.getElementById('filterForm');
    const params = new URLSearchParams(new FormData(filterForm)).toString();
    const exportUrl = `{{ route('mind-speak.export') }}?${params}`;
    window.location.href = exportUrl;
  }

  // Auto-dismiss alerts after 4s
  document.querySelectorAll('.ms-alert').forEach(el => {
    setTimeout(() => {
      const bsAlert = new bootstrap.Alert(el);
      bsAlert.close();
    }, 4000);
  });
</script>
@endsection
