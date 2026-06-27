@extends('layouts.layoutMaster')

@section('title', 'Verify Travel Claims')

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
                <h4 class="greeting text-white mb-1">Verify Travel Claims</h4>
                <p class="sub-text text-white-50 mb-0">Review and verify travel expenses submitted by employees.</p>
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

    <div class="hitech-card shadow-sm border-0">
        <div class="hitech-card-header">
            <h5 class="title mb-0 d-flex align-items-center gap-2" style="color: #005a5a;">
                <i class="bx bx-list-check fs-4 text-primary" style="color: #0d9488 !important;"></i> 
                <span>Pending Verifications</span>
            </h5>
        </div>
        <div class="card-body p-4">
            <div class="table-responsive">
                <table class="table table-flush-spacing align-middle mb-0">
                    <thead class="bg-light">
                        <tr>
                            <th class="text-uppercase" style="font-size: 0.75rem; letter-spacing: 0.5px;">Claim ID</th>
                            <th class="text-uppercase" style="font-size: 0.75rem; letter-spacing: 0.5px;">Employee</th>
                            <th class="text-uppercase" style="font-size: 0.75rem; letter-spacing: 0.5px;">Month</th>
                            <th class="text-uppercase" style="font-size: 0.75rem; letter-spacing: 0.5px;">Company</th>
                            <th class="text-uppercase" style="font-size: 0.75rem; letter-spacing: 0.5px;">Net Payable</th>
                            <th class="text-uppercase" style="font-size: 0.75rem; letter-spacing: 0.5px;">Remarks / Objection</th>
                            <th class="text-uppercase" style="font-size: 0.75rem; letter-spacing: 0.5px;">Status</th>
                            <th class="text-uppercase" style="font-size: 0.75rem; letter-spacing: 0.5px;">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($claims as $claim)
                        <tr>
                            <td><strong>#{{ $claim->id }}</strong></td>
                            <td>{{ $claim->user->name }}</td>
                            <td>{{ $claim->claim_month }}</td>
                            <td>{{ $claim->company }}</td>
                            <td>
                                <strong>₹{{ number_format($claim->net_payable, 2) }}</strong>
                                @if($claim->late_penalty_applied)
                                    <br><span class="badge bg-danger">Late Penalty Applied</span>
                                @endif
                            </td>
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
                                <span class="badge bg-{{ $claim->status == 'submitted' ? 'info' : ($claim->status == 'objection' ? 'warning text-dark' : 'secondary') }}">{{ ucfirst($claim->status) }}</span>
                            </td>
                            <td>
                                @if($claim->status === 'draft')
                                    <a href="{{ route('travel-claims.edit', $claim->id) }}" class="btn btn-sm btn-outline-warning rounded-pill px-3">Continue Draft</a>
                                    <form action="{{ route('travel-claims.destroy', $claim->id) }}" method="POST" class="d-inline" onsubmit="return confirm('Are you sure you want to delete this draft claim? This action cannot be undone.');">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="btn btn-sm btn-outline-danger rounded-pill px-3">Delete Draft</button>
                                    </form>
                                @else
                                    <button type="button" class="btn btn-sm btn-hitech rounded-pill px-3" data-bs-toggle="modal" data-bs-target="#verifyModal{{ $claim->id }}">Review</button>
                                @endif
                            </td>
                        </tr>

                        @empty
                        <tr>
                            <td colspan="7" class="text-center py-5 text-muted">No claims pending verification.</td>
                        </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

{{-- Render Modals --}}
@foreach($claims as $claim)
<!-- Review Modal -->
<div class="modal fade" id="verifyModal{{ $claim->id }}" tabindex="-1">
    <div class="modal-dialog modal-xl">
        <div class="modal-content border-0 shadow-lg">
            <div class="modal-header" style="background: linear-gradient(90deg, #005a5a, #007a7a); padding: 1.5rem;">
                <h5 class="modal-title text-white fw-bold mb-0">CLAIM #{{ $claim->id }} - {{ strtoupper($claim->user->name) }}</h5>
                <a href="{{ route('travel-claims.download-attachments', $claim->id) }}" class="btn btn-sm btn-light ms-auto me-3" style="border-radius: 8px;">
                    <i class="bx bx-download"></i> Download Attachments
                </a>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
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
                            @foreach($claim->items as $item)
                            <tr>
                                <td>{{ $item->date->format('d M') }}</td>
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
                        </tbody>
                    </table>
                </div>
            </div>
            <div class="modal-footer border-top d-block">
                <form id="verifyForm{{ $claim->id }}" method="POST" action="" class="w-100 m-0">
                    @csrf
                    <div class="row g-3 align-items-center">
                        <div class="col-md-7">
                            <input type="text" id="remarksInput{{ $claim->id }}" name="remarks" class="form-control" placeholder="Enter remarks / objection reason / rejection reason..." style="border-radius: 8px;">
                        </div>
                        <div class="col-md-5 text-end">
                            <div class="btn-group gap-2">
                                <button type="submit" onclick="submitClaimAction({{ $claim->id }}, '{{ route('travel-claims.verify', $claim->id) }}', false)" class="btn btn-success" style="border-radius: 8px;"><i class="bx bx-check"></i> Verify</button>
                                <button type="submit" onclick="submitClaimAction({{ $claim->id }}, '{{ route('travel-claims.objection', $claim->id) }}', true)" class="btn btn-warning text-white" style="border-radius: 8px;"><i class="bx bx-error-circle"></i> Objection</button>
                                <button type="submit" onclick="submitClaimAction({{ $claim->id }}, '{{ route('travel-claims.reject', $claim->id) }}', true)" class="btn btn-danger" style="border-radius: 8px;"><i class="bx bx-x-circle"></i> Reject</button>
                            </div>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
@endforeach

@section('page-script')
<script>
function submitClaimAction(claimId, routeUrl, requireRemarks) {
    let form = document.getElementById('verifyForm' + claimId);
    let remarksInput = document.getElementById('remarksInput' + claimId);
    if (requireRemarks && !remarksInput.value.trim()) {
        remarksInput.setCustomValidity('Remarks are required for objection or rejection.');
        remarksInput.reportValidity();
        event.preventDefault();
        return false;
    } else {
        remarksInput.setCustomValidity('');
    }
    form.action = routeUrl;
}
</script>
@endsection
@endsection
