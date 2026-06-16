@extends('layouts.layoutMaster')

@section('title', 'My Travel Claims')

@section('vendor-style')
  @vite([
    'resources/assets/vendor/scss/pages/hitech-portal.scss'
  ])
@endsection

@section('content')
<div class="container-fluid animate__animated animate__fadeIn">
    {{-- Hero Header --}}
    <div class="hitech-page-hero mb-6">
        <div class="hitech-page-hero-text d-flex justify-content-between align-items-center w-100">
            <div>
                <h4 class="greeting text-white mb-1">My Travel Claims</h4>
                <p class="sub-text text-white-50 mb-0">Track and submit your monthly travel and expense claims.</p>
            </div>
            <div>
                <a href="{{ route('travel-claims.create') }}" class="btn btn-hitech btn-light fw-bold shadow-sm rounded-pill px-4" style="color: #005a5a !important;">
                    <i class="bx bx-plus me-1"></i> New Claim
                </a>
            </div>
        </div>
    </div>

    @if(session('success'))
        <div class="alert alert-success">{{ session('success') }}</div>
    @endif

    <div class="card shadow-sm border-0">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>ID</th>
                            <th>Month</th>
                            <th>Company</th>
                            <th>Total Amount</th>
                            <th>Net Payable</th>
                            <th>Status</th>
                            <th>Payment Info</th>
                            <th>Submitted At</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($claims as $claim)
                        <tr>
                            <td><strong>#{{ $claim->id }}</strong></td>
                            <td>{{ $claim->claim_month }}</td>
                            <td>{{ $claim->company }}</td>
                            <td>₹{{ number_format($claim->total_amount, 2) }}</td>
                            <td>
                                <strong>₹{{ number_format($claim->net_payable, 2) }}</strong>
                                @if($claim->late_penalty_applied)
                                    <br><span class="badge bg-danger">10% Late Penalty</span>
                                @endif
                            </td>
                            <td>
                                @if($claim->status == 'draft') <span class="badge bg-secondary">Draft</span>
                                @elseif($claim->status == 'submitted') <span class="badge bg-info">Pending HR</span>
                                @elseif($claim->status == 'verified') <span class="badge bg-primary">Verified</span>
                                @elseif($claim->status == 'approved') <span class="badge bg-warning text-dark">Approved for Payout</span>
                                @elseif($claim->status == 'paid') <span class="badge bg-success">Paid</span>
                                @else <span class="badge bg-dark">{{ ucfirst($claim->status) }}</span>
                                @endif
                            </td>
                            <td>
                                @if($claim->status == 'approved' || $claim->status == 'paid')
                                    <small class="d-block text-muted">85%: ₹{{ $claim->split_85_amount }} on {{ $claim->split_85_paid_on ? $claim->split_85_paid_on->format('d M') : '11th' }}</small>
                                    <small class="d-block text-muted">15%: ₹{{ $claim->split_15_amount }} on {{ $claim->split_15_paid_on ? $claim->split_15_paid_on->format('d M') : '25th' }}</small>
                                @else
                                    <span class="text-muted">-</span>
                                @endif
                            </td>
                            <td>{{ $claim->created_at->format('d M, Y') }}</td>
                            <td>
                                <button type="button" class="btn btn-sm btn-outline-primary" data-bs-toggle="modal" data-bs-target="#viewModal{{ $claim->id }}">View Details</button>
                                @if($claim->status === 'objection')
                                    <a href="{{ route('travel-claims.edit', $claim->id) }}" class="btn btn-sm btn-outline-warning mt-1">Edit & Resubmit</a>
                                @endif
                            </td>
                        </tr>

                        <!-- View Details Modal -->
                        <div class="modal fade" id="viewModal{{ $claim->id }}" tabindex="-1">
                            <div class="modal-dialog modal-xl">
                                <div class="modal-content">
                                    <div class="modal-header bg-light">
                                        <h5 class="modal-title">Claim #{{ $claim->id }} Details</h5>
                                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                    </div>
                                    <div class="modal-body p-0">
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
                                                        <td>{{ $item->date->format('d M') }}</td>
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
                                                                <a href="{{ \Illuminate\Support\Facades\Storage::url($item->photo_path) }}" target="_blank" class="btn btn-xs btn-outline-primary">View</a>
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
                                    </div>
                                </div>
                            </div>
                        </div>
                        @empty
                        <tr>
                            <td colspan="8" class="text-center py-5 text-muted">You have not submitted any travel claims yet.</td>
                        </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
@endsection
