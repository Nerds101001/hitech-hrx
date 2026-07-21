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
                                    <div class="d-flex gap-2 align-items-center">
                                        @if($claim->user_id === auth()->id())
                                            {{-- Your own claim: view only. Nobody may action their own
                                                 claim (the controller blocks it server-side too). --}}
                                            <button type="button" class="btn btn-sm btn-outline-secondary rounded-pill px-3" data-bs-toggle="modal" data-bs-target="#verifyModal{{ $claim->id }}">View</button>
                                            <span class="badge bg-label-secondary" style="font-size:.7rem;">Your claim</span>
                                        @else
                                            <button type="button" class="btn btn-sm btn-hitech rounded-pill px-3" data-bs-toggle="modal" data-bs-target="#verifyModal{{ $claim->id }}">Review</button>
                                        @endif
                                        <a href="{{ route('travel-claims.download-attachments', $claim->id) }}" class="btn btn-sm btn-outline-primary rounded-pill px-3 d-flex align-items-center gap-1" title="Download All Attachments">
                                            <i class="bx bx-download"></i> Download All
                                        </a>
                                    </div>
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
                                <th>Conveyance/<br>Amount (₹)</th>
                                <th>Conveyance/<br>Bill Proof</th>
                                <th>Food</th>
                                <th>Food Bill<br>Proof</th>
                                <th>Lodging</th>
                                <th>Courier (Transport/<br>Freight) etc</th>
                                <th>Courier / Transport<br>Proof</th>
                                <th>Toll/Parking</th>
                                <th>Toll/Parking<br>Proof</th>
                                <th>Customer Expense</th>
                                <th>Customer Expense Proof</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($claim->items as $item)
                            <tr>
                                <td>{{ $item->date->format('d M') }}</td>
                                <td>{{ $item->from_location }} -> {{ $item->to_location }}</td>
                                <td>{{ $item->party_visited }}</td>
                                <td>{{ $item->mode_of_travel }} <br> <strong>{{ $item->distance_km }} KM</strong></td                                <td>₹{{ number_format($item->conveyance_amount, 2) }}</td>
                                <td>
                                    @if($item->petrol_slip_proof)
                                        <button type="button" data-url="{{ \Illuminate\Support\Facades\Storage::url($item->petrol_slip_proof) }}" class="btn btn-xs btn-outline-primary btn-preview-attachment">View</button>
                                    @else
                                         <span class="text-muted">-</span>
                                    @endif
                                </td>
                                <td>₹{{ number_format($item->food_allowance, 2) }}</td>
                                <td>
                                    @if($item->additional_food_proof)
                                        <button type="button" data-url="{{ \Illuminate\Support\Facades\Storage::url($item->additional_food_proof) }}" class="btn btn-xs btn-outline-primary btn-preview-attachment">View</button>
                                    @else
                                         <span class="text-muted">-</span>
                                    @endif
                                </td>
                                <td>₹{{ number_format($item->lodging_amount, 2) }}</td>
                                <td>₹{{ number_format($item->courier_amount, 2) }}</td>
                                <td>
                                    @if($item->courier_proof)
                                        <button type="button" data-url="{{ \Illuminate\Support\Facades\Storage::url($item->courier_proof) }}" class="btn btn-xs btn-outline-primary btn-preview-attachment">View</button>
                                    @else
                                         <span class="text-muted">-</span>
                                    @endif
                                </td>
                                <td>₹{{ number_format($item->toll_amount, 2) }}</td>
                                <td>
                                    @if($item->toll_proof)
                                        <button type="button" data-url="{{ \Illuminate\Support\Facades\Storage::url($item->toll_proof) }}" class="btn btn-xs btn-outline-primary btn-preview-attachment">View</button>
                                    @else
                                         <span class="text-muted">-</span>
                                    @endif
                                </td>
                                <td>₹{{ number_format($item->special_approval_amount ?? 0, 2) }}</td>
                                <td>
                                    @if($item->special_approval_proof)
                                        <button type="button" data-url="{{ \Illuminate\Support\Facades\Storage::url($item->special_approval_proof) }}" class="btn btn-xs btn-outline-primary btn-preview-attachment">View</button>
                                    @else
                                         <span class="text-muted">-</span>
                                    @endif
                                </td>
                            </tr>                   </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                {{-- Consolidated monthly bill attachments --}}
                @php
                    $monthlyProofs = [
                        'Food Bills'                    => $claim->food_bill_proof,
                        'Conveyance / Petrol'           => $claim->conveyance_petrol_proof,
                        'Toll / Parking'                => $claim->toll_parking_proof,
                        'Customer Expense / Courier'    => $claim->customer_courier_proof,
                        'Month Start Odometer'          => $claim->month_start_odometer_proof,
                        'Month End Odometer'            => $claim->month_end_odometer_proof,
                    ];
                @endphp
                <div class="p-3 border-top bg-light">
                    <h6 class="fw-bold text-muted text-uppercase small mb-2"><i class="bx bx-paperclip"></i> Monthly Bill Attachments</h6>
                    <div class="d-flex flex-wrap gap-2">
                        @foreach($monthlyProofs as $label => $path)
                            @if($path)
                                <button type="button" data-url="{{ \Illuminate\Support\Facades\Storage::url($path) }}" class="btn btn-sm btn-outline-primary btn-preview-attachment">
                                    <i class="bx bx-file"></i> {{ $label }}
                                </button>
                            @else
                                <span class="btn btn-sm btn-outline-secondary disabled text-muted" style="opacity:.5;">{{ $label }}: none</span>
                            @endif
                        @endforeach
                    </div>
                </div>
            </div>
            <div class="modal-footer border-top d-block">
                <form id="verifyForm{{ $claim->id }}" method="POST" action="" class="w-100 m-0">
                    @csrf
                    <div class="row g-3 align-items-center">
                        @if($claim->user_id === auth()->id())
                            <div class="col-12">
                                <div class="alert alert-secondary mb-0 py-2" style="font-size:.85rem;">
                                    <i class="bx bx-info-circle me-1"></i>
                                    This is your own claim — it must be verified by someone else. You can view it here but cannot verify, object or reject it.
                                </div>
                            </div>
                        @else
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
                        @endif
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
@endforeach

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

document.addEventListener('DOMContentLoaded', function() {
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
@endsection
@endsection
