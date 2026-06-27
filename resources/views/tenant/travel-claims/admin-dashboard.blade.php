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
  /* ===================== Custom KPI Purple Card for Admin ===================== */
  .card-purple::after { background: linear-gradient(90deg, #8b5cf6, #c084fc) !important; }
  .icon-purple { background: rgba(139, 92, 246, 0.1); color: #7c3aed; }
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
      <div class="hitech-stat-card dashboard-variant card-blue h-100">
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
      <div class="hitech-stat-card dashboard-variant card-teal h-100">
        <div class="stat-card-header">
          <div class="stat-icon-wrap icon-teal"><i class="bx bx-time"></i></div>
        </div>
        <div>
          <h3 class="stat-value mb-1">{{ number_format($stats['pending']) }}</h3>
          <small class="stat-label">Pending</small>
        </div>
      </div>
    </div>
    <div class="col-12 col-sm-6 col-md-4 col-lg">
      <div class="hitech-stat-card dashboard-variant card-amber h-100">
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
      <div class="hitech-stat-card dashboard-variant card-red h-100">
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
      <div class="hitech-stat-card dashboard-variant card-purple h-100">
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
  <div class="hitech-card mb-4 p-3">
    <form method="GET" action="{{ route('travel-claims.adminDashboard') }}" id="filterForm">
      <div class="row g-3 align-items-end">
        <div class="col-12 col-sm-6 col-md-3">
          <label class="hitech-label">Status</label>
          <select name="status" class="form-select-hitech">
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
          <label class="hitech-label">Company</label>
          <select name="company" class="form-select-hitech">
            <option value="">All Companies</option>
            <option value="Hi Tech International" {{ request('company') == 'Hi Tech International' ? 'selected' : '' }}>Hi Tech</option>
            <option value="KEEP IT FRESH LLP" {{ request('company') == 'KEEP IT FRESH LLP' ? 'selected' : '' }}>KEEP IT FRESH</option>
          </select>
        </div>

        <div class="col-12 col-sm-6 col-md-3">
          <label class="hitech-label">Claim Month</label>
          <input type="month" name="claim_month" class="hitech-input" value="{{ request('claim_month') }}">
        </div>

        <div class="col-12 col-md-3">
          <div class="d-flex gap-2">
            <button type="submit" class="btn btn-hitech w-100 fw-bold d-flex align-items-center justify-content-center">
              <i class="bx bx-filter-alt me-2"></i> Filter
            </button>
            <a href="{{ route('travel-claims.adminDashboard') }}" class="btn btn-outline-secondary d-flex align-items-center justify-content-center" style="border-radius: 12px; min-width: 46px;" title="Reset">
              <i class="bx bx-reset fs-4"></i>
            </a>
          </div>
        </div>
      </div>
    </form>
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
          <div class="table-responsive">
            <table class="table table-flush-spacing mb-0" id="claimsTable">
              <thead class="bg-light">
                <tr>
                  <th class="text-uppercase" style="font-size: 0.75rem; letter-spacing: 0.5px;">ID</th>
                  <th class="text-uppercase" style="font-size: 0.75rem; letter-spacing: 0.5px;">Employee</th>
                  <th class="text-uppercase" style="font-size: 0.75rem; letter-spacing: 0.5px;">Month</th>
                  <th class="text-uppercase" style="font-size: 0.75rem; letter-spacing: 0.5px;">Net Payable</th>
                  <th class="text-uppercase" style="font-size: 0.75rem; letter-spacing: 0.5px;">Remarks / Objection</th>
                  <th class="text-uppercase" style="font-size: 0.75rem; letter-spacing: 0.5px;">Status</th>
                  <th class="text-uppercase" style="font-size: 0.75rem; letter-spacing: 0.5px;">Actions</th>
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
                      @if($claim->status == 'submitted' || $claim->status == 'verified')
                        <button class="btn btn-sm btn-hitech rounded-pill px-3" data-bs-toggle="modal" data-bs-target="#actionModal{{ $claim->id }}">Review</button>
                      @else
                        <button class="btn btn-sm btn-outline-secondary rounded-pill px-3" data-bs-toggle="modal" data-bs-target="#actionModal{{ $claim->id }}">View</button>
                      @endif
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
                  <th>Claim ID</th>
                  <th>Employee</th>
                  <th>Claim Month</th>
                  <th>Total Net Payable</th>
                  <th>85% Split (11th of next month)</th>
                  <th>85% Payout Status</th>
                  <th>15% Split (25th of next month)</th>
                  <th>15% Payout Status</th>
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
            <a href="{{ route('travel-claims.download-attachments', $claim->id) }}" class="btn btn-sm btn-light ms-auto me-3" style="border-radius: 8px;">
                <i class="bx bx-download"></i> Download Attachments
            </a>
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
                                    <a href="{{ \Illuminate\Support\Facades\Storage::url($item->photo_path) }}" target="_blank" class="btn btn-xs btn-outline-primary">View</a>
                                @else
                                     <span class="text-muted">-</span>
                                @endif
                            </td>
                            <td>
                                @if($item->petrol_slip_proof)
                                    <a href="{{ \Illuminate\Support\Facades\Storage::url($item->petrol_slip_proof) }}" target="_blank" class="btn btn-xs btn-outline-primary">View</a>
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
                                    <a href="{{ \Illuminate\Support\Facades\Storage::url($item->auto_taxi_proof) }}" target="_blank" class="btn btn-xs btn-outline-primary">View</a>
                                @else
                                     <span class="text-muted">-</span>
                                @endif
                            </td>
                            <td>₹{{ $item->food_allowance }}</td>
                            <td>₹{{ $item->lodging_amount }}</td>
                            <td>₹{{ $item->courier_amount }}</td>
                            <td>
                                @if($item->courier_proof)
                                    <a href="{{ \Illuminate\Support\Facades\Storage::url($item->courier_proof) }}" target="_blank" class="btn btn-xs btn-outline-info">View</a>
                                @else
                                    <span class="text-muted">-</span>
                                @endif
                            </td>
                            <td>₹{{ $item->transport_amount }}</td>
                            <td>
                                @if($item->transport_proof)
                                    <a href="{{ \Illuminate\Support\Facades\Storage::url($item->transport_proof) }}" target="_blank" class="btn btn-xs btn-outline-info">View</a>
                                @else
                                    <span class="text-muted">-</span>
                                @endif
                            </td>
                            <td>₹{{ $item->bills_amount }}</td>
                            <td>
                                @if($item->bills_proof)
                                    <a href="{{ \Illuminate\Support\Facades\Storage::url($item->bills_proof) }}" target="_blank" class="btn btn-xs btn-outline-info">View</a>
                                @else
                                    <span class="text-muted">-</span>
                                @endif
                            </td>
                            <td>₹{{ $item->freight_amount }}</td>
                            <td>
                                @if($item->freight_proof)
                                    <a href="{{ \Illuminate\Support\Facades\Storage::url($item->freight_proof) }}" target="_blank" class="btn btn-xs btn-outline-info">View</a>
                                @else
                                    <span class="text-muted">-</span>
                                @endif
                            </td>
                            <td>₹{{ $item->toll_amount }}</td>
                            <td>
                                @if($item->toll_proof)
                                    <a href="{{ \Illuminate\Support\Facades\Storage::url($item->toll_proof) }}" target="_blank" class="btn btn-xs btn-outline-info">View</a>
                                @else
                                    <span class="text-muted">-</span>
                                @endif
                            </td>
                            <td>₹{{ $item->additional_food_amount ?? 0 }}</td>
                            <td>
                                @if($item->additional_food_proof)
                                    <a href="{{ \Illuminate\Support\Facades\Storage::url($item->additional_food_proof) }}" target="_blank" class="btn btn-xs btn-outline-info">View</a>
                                @else
                                    <span class="text-muted">-</span>
                                @endif
                            </td>
                            <td>₹{{ $item->special_approval_amount ?? 0 }}</td>
                            <td>
                                @if($item->special_approval_proof)
                                    <a href="{{ \Illuminate\Support\Facades\Storage::url($item->special_approval_proof) }}" target="_blank" class="btn btn-xs btn-outline-info">View</a>
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
                  <div class="d-flex gap-2">
                    <input type="text" name="remarks" class="form-control" placeholder="Verification Remarks (Optional)" style="border-radius: 8px;">
                    <button type="submit" class="btn btn-success text-nowrap" style="border-radius: 8px;">Approve & Verify</button>
                  </div>
              </form>
              @endif

              <div class="row g-3">
                  <div class="col-6">
                      <form action="{{ route('travel-claims.objection', $claim->id) }}" method="POST">
                          @csrf
                          <div class="d-flex gap-2">
                            <input type="text" name="remarks" class="form-control" placeholder="Objection Reason" required style="border-radius: 8px;">
                            <button type="submit" class="btn btn-warning text-nowrap" style="border-radius: 8px;">Raise Objection</button>
                          </div>
                      </form>
                  </div>
                  <div class="col-6">
                      <form action="{{ route('travel-claims.reject', $claim->id) }}" method="POST">
                          @csrf
                          <div class="d-flex gap-2">
                            <input type="text" name="remarks" class="form-control" placeholder="Rejection Reason" required style="border-radius: 8px;">
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
  });
</script>
@endsection
