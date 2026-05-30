@extends('layouts.layoutMaster')

@section('title', 'Travel & Expense Admin Dashboard')

@section('vendor-style')
  @vite([
    'resources/assets/vendor/scss/pages/hitech-portal.scss'
  ])
@endsection

@section('page-style')
<style>
  /* ===================== Custom KPI Purple Card for Admin ===================== */
  .card-purple::after { background: linear-gradient(90deg, #8b5cf6, #c084fc); }
  .icon-purple { background: rgba(139, 92, 246, 0.1); color: #7c3aed; }
  .card-blue::after { background: linear-gradient(90deg, #3b82f6, #60a5fa); }
  .icon-blue { background: rgba(59, 130, 246, 0.1); color: #3b82f6; }
  .card-teal::after { background: linear-gradient(90deg, #14b8a6, #2dd4bf); }
  .icon-teal { background: rgba(20, 184, 166, 0.1); color: #14b8a6; }
  .card-amber::after { background: linear-gradient(90deg, #f59e0b, #fbbf24); }
  .icon-amber { background: rgba(245, 158, 11, 0.1); color: #f59e0b; }
  .card-red::after { background: linear-gradient(90deg, #ef4444, #f87171); }
  .icon-red { background: rgba(239, 68, 68, 0.1); color: #ef4444; }

  /* Stat Card Style fallback if hitech-stat-card css is missing */
  .hitech-stat-card { position: relative; background: #fff; padding: 1.5rem; border-radius: 12px; box-shadow: 0 2px 12px rgba(0,0,0,0.04); overflow: hidden; }
  .hitech-stat-card::after { content: ''; position: absolute; bottom: 0; left: 0; width: 100%; height: 4px; }
  .stat-card-header { margin-bottom: 1rem; }
  .stat-icon-wrap { width: 48px; height: 48px; border-radius: 12px; display: flex; align-items: center; justify-content: center; font-size: 1.5rem; }
  .stat-value { font-weight: 700; font-size: 1.75rem; color: #1e293b; }
  .stat-label { color: #64748b; font-weight: 500; }
</style>
@endsection

@section('content')
<div class="animate__animated animate__fadeIn">

  @if(session('success'))
    <div class="alert alert-success alert-dismissible fade show" role="alert">
      <i class="bx bx-check-circle me-2 fs-5 align-middle"></i> {{ session('success') }}
      <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
  @endif

  {{-- Hero Header --}}
  <div class="hitech-page-hero mb-6">
    <div class="hitech-page-hero-text">
      <h4 class="greeting text-white mb-1">Travel & Expense Admin Dashboard</h4>
      <p class="sub-text text-white-50 mb-0">High-level view of all travel claims, approvals, rejections, and payouts across the organization.</p>
    </div>
  </div>

  {{-- KPI Stat Cards --}}
  <div class="row g-4 mb-4">
    <div class="col-12 col-sm-6 col-md-4 col-lg">
      <div class="hitech-stat-card card-blue h-100">
        <div class="stat-card-header">
          <div class="stat-icon-wrap icon-blue"><i class="bx bx-file"></i></div>
        </div>
        <div>
          <h3 class="stat-value mb-1">{{ number_format($stats['total']) }}</h3>
          <small class="stat-label">Total Claims</small>
        </div>
      </div>
    </div>
    <div class="col-12 col-sm-6 col-md-4 col-lg">
      <div class="hitech-stat-card card-teal h-100">
        <div class="stat-card-header">
          <div class="stat-icon-wrap icon-teal"><i class="bx bx-time"></i></div>
        </div>
        <div>
          <h3 class="stat-value mb-1">{{ number_format($stats['pending']) }}</h3>
          <small class="stat-label">Pending Verification</small>
        </div>
      </div>
    </div>
    <div class="col-12 col-sm-6 col-md-4 col-lg">
      <div class="hitech-stat-card card-amber h-100">
        <div class="stat-card-header">
          <div class="stat-icon-wrap icon-amber"><i class="bx bx-check-shield"></i></div>
        </div>
        <div>
          <h3 class="stat-value mb-1">{{ number_format($stats['approved']) }}</h3>
          <small class="stat-label">Approved & Paid</small>
        </div>
      </div>
    </div>
    <div class="col-12 col-sm-6 col-md-4 col-lg">
      <div class="hitech-stat-card card-red h-100">
        <div class="stat-card-header">
          <div class="stat-icon-wrap icon-red"><i class="bx bx-x-circle"></i></div>
        </div>
        <div>
          <h3 class="stat-value mb-1">{{ number_format($stats['rejected']) }}</h3>
          <small class="stat-label">Rejected</small>
        </div>
      </div>
    </div>
    <div class="col-12 col-sm-6 col-md-4 col-lg">
      <div class="hitech-stat-card card-purple h-100">
        <div class="stat-card-header">
          <div class="stat-icon-wrap icon-purple"><i class="bx bx-rupee"></i></div>
        </div>
        <div>
          <h3 class="stat-value mb-1">₹{{ number_format($stats['total_payout']) }}</h3>
          <small class="stat-label">Total Payout Volume</small>
        </div>
      </div>
    </div>
  </div>

  {{-- Filter & Sort Form --}}
  <div class="filter-bar">
    <form method="GET" action="{{ route('travel-claims.adminDashboard') }}" id="filterForm">
      <div class="row g-3 align-items-end">
        <div class="col-12 col-sm-6 col-md-3">
          <label class="form-label">Status</label>
          <select name="status" class="form-select">
            <option value="">All Statuses</option>
            <option value="submitted" {{ request('status') == 'submitted' ? 'selected' : '' }}>Pending Verification</option>
            <option value="verified" {{ request('status') == 'verified' ? 'selected' : '' }}>Verified</option>
            <option value="approved" {{ request('status') == 'approved' ? 'selected' : '' }}>Approved</option>
            <option value="paid" {{ request('status') == 'paid' ? 'selected' : '' }}>Paid</option>
            <option value="objection" {{ request('status') == 'objection' ? 'selected' : '' }}>Objection</option>
            <option value="rejected" {{ request('status') == 'rejected' ? 'selected' : '' }}>Rejected</option>
          </select>
        </div>

        <div class="col-12 col-sm-6 col-md-3">
          <label class="form-label">Company</label>
          <select name="company" class="form-select">
            <option value="">All Companies</option>
            <option value="Hi Tech International" {{ request('company') == 'Hi Tech International' ? 'selected' : '' }}>Hi Tech</option>
            <option value="KEEP IT FRESH LLP" {{ request('company') == 'KEEP IT FRESH LLP' ? 'selected' : '' }}>KEEP IT FRESH</option>
          </select>
        </div>

        <div class="col-12 col-sm-6 col-md-3">
          <label class="form-label">Claim Month</label>
          <input type="month" name="claim_month" class="form-control" value="{{ request('claim_month') }}">
        </div>

        <div class="col-12 col-md-3">
          <div class="d-flex gap-2">
            <button type="submit" class="btn btn-filter w-100 fw-bold">
              <i class="bx bx-filter-alt me-1"></i> Filter
            </button>
            <a href="{{ route('travel-claims.adminDashboard') }}" class="btn btn-outline-secondary d-flex align-items-center justify-content-center" style="border-radius: 9px; height: 40px; width: 40px; padding: 0;" title="Reset">
              <i class="bx bx-reset fs-5"></i>
            </a>
          </div>
        </div>
      </div>
    </form>
  </div>

  {{-- Chart and Tables --}}
  <div class="row g-4 mb-4">
    <div class="col-12 col-lg-4">
      <div class="card ms-card h-100">
        <div class="ms-card-header">
          <h5 class="ms-card-title"><i class="bx bx-bar-chart me-1 text-primary"></i> Claims Volume per Month</h5>
        </div>
        <div class="card-body p-4">
          <canvas id="monthlyTrendChart" style="max-height: 280px; width: 100%;"></canvas>
        </div>
      </div>
    </div>
    <div class="col-12 col-lg-8">
      <div class="card ms-card">
        <div class="ms-card-header">
          <h5 class="ms-card-title"><i class="bx bx-list-ul me-1 text-primary"></i> All Claims List</h5>
        </div>
        <div class="card-body p-0">
          <div class="table-responsive">
            <table class="table ms-table mb-0">
              <thead>
                <tr>
                  <th>ID</th>
                  <th>Employee</th>
                  <th>Month</th>
                  <th>Net Payable</th>
                  <th>Status</th>
                  <th>Actions</th>
                </tr>
              </thead>
              <tbody>
                @forelse($claims as $claim)
                  <tr>
                    <td>#{{ $claim->id }}</td>
                    <td>
                      <strong>{{ $claim->user->name }}</strong><br>
                      <small class="text-muted">{{ $claim->company }}</small>
                    </td>
                    <td>{{ $claim->claim_month }}</td>
                    <td>₹{{ number_format($claim->net_payable, 2) }}</td>
                    <td>
                      <span class="badge bg-{{ $claim->status == 'submitted' ? 'info' : ($claim->status == 'approved' ? 'success' : 'secondary') }}">
                        {{ ucfirst($claim->status) }}
                      </span>
                    </td>
                    <td>
                      @if($claim->status == 'submitted' || $claim->status == 'verified')
                        <button class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#actionModal{{ $claim->id }}">Action</button>
                      @else
                        <button class="btn btn-sm btn-outline-secondary" data-bs-toggle="modal" data-bs-target="#actionModal{{ $claim->id }}">View</button>
                      @endif
                    </td>
                  </tr>

                  <!-- Action Modal -->
                  <div class="modal fade" id="actionModal{{ $claim->id }}" tabindex="-1">
                    <div class="modal-dialog modal-xl">
                      <div class="modal-content border-0 shadow-lg">
                        <div class="modal-header bg-light">
                          <h5 class="modal-title">Claim #{{ $claim->id }} - {{ $claim->user->name }}</h5>
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
                                <p class="mb-1"><strong>Bank:</strong> {{ $claim->bank_account_name }} ({{ $claim->bank_account_no }})</p>
                                <p class="mb-1"><strong>IFSC:</strong> {{ $claim->bank_ifsc }}</p>
                              </div>
                              <div class="col-md-4 text-end">
                                <p class="mb-1"><strong>Gross Total:</strong> ₹{{ $claim->total_amount }}</p>
                                <p class="mb-1"><strong>Advances:</strong> ₹{{ $claim->total_advances }}</p>
                                <h5 class="text-primary mb-0"><strong>Net Payable:</strong> ₹{{ $claim->net_payable }}</h5>
                              </div>
                            </div>
                          </div>

                          <div class="table-responsive">
                              <table class="table table-bordered mb-0" style="font-size: 0.8rem;">
                                  <thead class="table-light">
                                      <tr>
                                          <th>Date</th>
                                          <th>Locations</th>
                                          <th>Party</th>
                                          <th>Mode & KMs</th>
                                          <th>Conveyance</th>
                                          <th>Food</th>
                                          <th>Lodging</th>
                                          <th>Courier</th>
                                          <th>Photo Proof</th>
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
                                          <td>₹{{ $item->conveyance_amount }}
                                              @if($item->penalty_applied) <br><span class="text-danger small">No Photo Penalty</span> @endif
                                          </td>
                                          <td>₹{{ $item->food_allowance }}</td>
                                          <td>₹{{ $item->lodging_amount }}</td>
                                          <td>₹{{ $item->courier_amount }}</td>
                                          <td>
                                              @if($item->photo_path)
                                                  <a href="{{ Storage::url($item->photo_path) }}" target="_blank" class="btn btn-xs btn-outline-primary">View</a>
                                              @else
                                                  <span class="text-muted">N/A</span>
                                              @endif
                                          </td>
                                      </tr>
                                      @endforeach
                                      @endif
                                  </tbody>
                              </table>
                          </div>

                          <div class="p-3 bg-light border-top">
                          @if(in_array($claim->status, ['submitted', 'verified']))
                            <!-- Approve/Verify -->
                            @if($claim->status == 'submitted')
                            <form action="{{ route('travel-claims.verify', $claim->id) }}" method="POST" class="mb-3">
                                @csrf
                                <div class="input-group">
                                  <input type="text" name="remarks" class="form-control" placeholder="Verification Remarks (Optional)">
                                  <button type="submit" class="btn btn-success">Approve & Verify</button>
                                </div>
                            </form>
                            @endif

                            <div class="row">
                                <div class="col-6">
                                    <form action="{{ route('travel-claims.objection', $claim->id) }}" method="POST">
                                        @csrf
                                        <div class="input-group">
                                          <input type="text" name="remarks" class="form-control" placeholder="Objection Reason" required>
                                          <button type="submit" class="btn btn-warning">Raise Objection</button>
                                        </div>
                                    </form>
                                </div>
                                <div class="col-6">
                                    <form action="{{ route('travel-claims.reject', $claim->id) }}" method="POST">
                                        @csrf
                                        <div class="input-group">
                                          <input type="text" name="remarks" class="form-control" placeholder="Rejection Reason" required>
                                          <button type="submit" class="btn btn-danger">Reject Claim</button>
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
                @empty
                  <tr>
                    <td colspan="6" class="text-center py-5">No claims found.</td>
                  </tr>
                @endforelse
              </tbody>
            </table>
          </div>
          <div class="p-3 d-flex justify-content-center">
            {{ $claims->appends(request()->query())->links() }}
          </div>
        </div>
      </div>
    </div>
  </div>

</div>
@endsection

@section('page-script')
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
  (function() {
    const ctx = document.getElementById('monthlyTrendChart');
    if (!ctx) return;

    const months = @json(array_keys($monthlyStats));
    const counts = @json(array_values($monthlyStats));

    new Chart(ctx, {
      type: 'line',
      data: {
        labels: months,
        datasets: [{
          label: 'Claims Submitted',
          data: counts,
          backgroundColor: 'rgba(59, 130, 246, 0.2)',
          borderColor: '#3b82f6',
          borderWidth: 2,
          fill: true,
          tension: 0.3
        }]
      },
      options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
          legend: { display: false }
        },
        scales: {
          x: { grid: { display: false } },
          y: { beginAtZero: true, ticks: { stepSize: 1 } }
        }
      }
    });
  })();
</script>
@endsection
