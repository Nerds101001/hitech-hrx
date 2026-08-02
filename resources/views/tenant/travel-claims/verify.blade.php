@extends('layouts.layoutMaster')

@section('title', 'Verify Travel Claims')

@section('vendor-style')
  @vite([
    'resources/assets/vendor/scss/pages/hitech-portal.scss'
  ])
@endsection

@section('content')
<style>
    /* --- Claims list: keep rows one line tall and actions on a common baseline --- */
    .claims-table thead th {
        background: #f6f8f9;
        color: #6b7a7a;
        font-size: .7rem;
        font-weight: 600;
        letter-spacing: .4px;
        text-transform: uppercase;
        white-space: nowrap;
        padding: .65rem .75rem;
        border-bottom: 1px solid #e6ecec;
    }
    .claims-table tbody td {
        padding: .7rem .75rem;
        font-size: .82rem;
        vertical-align: middle;
        border-bottom: 1px solid #f0f3f3;
    }
    .claims-table tbody tr:hover { background: #fafbfb; }

    .claim-company {
        max-width: 170px;
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
    }

    /* Remarks stay to two lines — the full text is in the title tooltip */
    .claim-remarks { max-width: 320px; min-width: 200px; }
    .claim-note {
        display: -webkit-box;
        -webkit-line-clamp: 2;
        -webkit-box-orient: vertical;
        overflow: hidden;
        font-size: .78rem;
        line-height: 1.35;
    }
    .claim-note-objection {
        color: #b02a37;
        padding-left: .5rem;
        border-left: 2px solid #dc3545;
    }
    .claim-note-objection i { display: none; }

    .claim-own-tag {
        display: inline-block;
        margin-top: .15rem;
        font-size: .65rem;
        font-weight: 600;
        letter-spacing: .3px;
        text-transform: uppercase;
        color: #8a9a9a;
    }

    .claim-status {
        display: inline-block;
        min-width: 84px;
        text-align: center;
        font-weight: 600;
        font-size: .7rem;
    }

    /* Every row's buttons align right, same size, never wrap */
    .claim-actions {
        display: flex;
        justify-content: flex-end;
        align-items: center;
        gap: .4rem;
        flex-wrap: nowrap;
    }
    .claim-actions form { margin: 0; }
    .claim-actions .btn {
        white-space: nowrap;
        font-size: .75rem;
        padding: .3rem .7rem;
        border-radius: 6px;
        min-width: 86px;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        gap: .25rem;
    }
    .claim-actions .btn i { font-size: .9rem; }

    tr.flagged-row > td { background-color: #fdecea !important; }
    tr.flagged-row > td:first-child { box-shadow: inset 3px 0 0 0 #dc3545; }
</style>
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
                <table class="table align-middle mb-0 claims-table">
                    <thead>
                        <tr>
                            <th style="width:80px;">Claim</th>
                            <th style="width:170px;">Employee</th>
                            <th style="width:90px;">Month</th>
                            <th style="width:170px;">Company</th>
                            <th style="width:120px;" class="text-end">Net Payable</th>
                            <th>Remarks / Objection</th>
                            <th style="width:110px;">Status</th>
                            <th style="width:200px;" class="text-end">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($claims as $claim)
                        <tr>
                            <td class="text-nowrap fw-semibold">#{{ $claim->id }}</td>
                            <td>
                                <div class="text-nowrap">{{ $claim->user->name }}</div>
                                @if($claim->user_id === auth()->id())
                                    <span class="claim-own-tag">Your claim</span>
                                @endif
                            </td>
                            <td class="text-nowrap">{{ $claim->claim_month }}</td>
                            <td class="claim-company">{{ $claim->company }}</td>
                            <td class="text-nowrap text-end">
                                <span class="fw-semibold">₹{{ number_format($claim->net_payable, 2) }}</span>
                                @if($claim->late_penalty_applied)
                                    <div><span class="badge bg-label-danger mt-1" style="font-size:.65rem;">Late penalty</span></div>
                                @endif
                            </td>
                            <td class="claim-remarks">
                                @if($claim->objection_notes)
                                    <div class="claim-note claim-note-objection" title="{{ $claim->objection_notes }}">
                                        <i class="bx bx-error-circle"></i>
                                        <span>{{ $claim->objection_notes }}</span>
                                    </div>
                                @elseif($claim->remarks)
                                    <div class="claim-note text-muted" title="{{ $claim->remarks }}">{{ $claim->remarks }}</div>
                                @else
                                    <span class="text-muted">—</span>
                                @endif
                            </td>
                            <td class="text-nowrap">
                                @php
                                    $statusStyles = [
                                        'draft'     => 'bg-label-secondary',
                                        'submitted' => 'bg-label-info',
                                        'objection' => 'bg-label-danger',
                                        'verified'  => 'bg-label-primary',
                                        'approved'  => 'bg-label-success',
                                        'rejected'  => 'bg-label-danger',
                                        'paid'      => 'bg-label-success',
                                    ];
                                @endphp
                                <span class="badge {{ $statusStyles[$claim->status] ?? 'bg-label-secondary' }} claim-status">{{ ucfirst($claim->status) }}</span>
                            </td>
                            <td>
                                <div class="claim-actions">
                                    @if($claim->status === 'draft')
                                        <a href="{{ route('travel-claims.edit', $claim->id) }}" class="btn btn-sm btn-outline-primary">
                                            <i class="bx bx-edit-alt"></i> Continue
                                        </a>
                                        <form action="{{ route('travel-claims.destroy', $claim->id) }}" method="POST" onsubmit="return confirm('Delete this draft claim? This action cannot be undone.');">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="btn btn-sm btn-outline-danger">
                                                <i class="bx bx-trash"></i> Delete
                                            </button>
                                        </form>
                                    @else
                                        {{-- Your own claim: view only. Nobody may action their own
                                             claim (the controller blocks it server-side too). --}}
                                        <button type="button" class="btn btn-sm {{ $claim->user_id === auth()->id() ? 'btn-outline-secondary' : 'btn-primary' }}"
                                                data-bs-toggle="modal" data-bs-target="#verifyModal{{ $claim->id }}">
                                            <i class="bx {{ $claim->user_id === auth()->id() ? 'bx-show' : 'bx-check-shield' }}"></i>
                                            {{ $claim->user_id === auth()->id() ? 'View' : 'Review' }}
                                        </button>
                                        <a href="{{ route('travel-claims.download-attachments', $claim->id) }}" class="btn btn-sm btn-outline-secondary" title="Download all attachments">
                                            <i class="bx bx-download"></i> Files
                                        </a>
                                    @endif
                                </div>
                            </td>
                        </tr>

                        @empty
                        <tr>
                            <td colspan="8" class="text-center py-5 text-muted">No claims pending verification.</td>
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
                                @if($claim->user_id !== auth()->id())
                                <th title="Tick the rows you are objecting to" style="width:52px;">Flag</th>
                                @endif
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
                            <tr class="{{ $item->is_flagged ? 'flagged-row' : '' }}">
                                @if($claim->user_id !== auth()->id())
                                <td class="text-center align-middle">
                                    <input type="checkbox" class="form-check-input flag-item flag-item-{{ $claim->id }}"
                                           value="{{ $item->id }}" {{ $item->is_flagged ? 'checked' : '' }}
                                           title="Flag this row for correction">
                                </td>
                                @endif
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

                    {{-- Bills uploaded through the multi-file boxes, listed per
                         category. The single-file buttons above stay for claims
                         saved before that change. --}}
                    @php
                        $attachLabels = [
                            'food_bill_proof'         => 'Food Bills',
                            'conveyance_petrol_proof' => 'Conveyance / Petrol',
                            'toll_parking_proof'      => 'Toll / Parking',
                            'customer_courier_proof'  => 'Customer Expense / Courier',
                        ];
                        $grouped = $claim->attachments->groupBy('category');
                    @endphp
                    @if($claim->attachments->count())
                        <div class="mt-3">
                            @foreach($attachLabels as $cat => $label)
                                @php $files = $grouped->get($cat, collect()); @endphp
                                @if($files->count())
                                    <div class="mb-2">
                                        <div class="small fw-semibold text-muted">{{ $label }} ({{ $files->count() }})</div>
                                        <div class="d-flex flex-wrap gap-1 mt-1">
                                            @foreach($files as $f)
                                                <button type="button" data-url="{{ \Illuminate\Support\Facades\Storage::url($f->path) }}"
                                                        class="btn btn-xs btn-outline-primary btn-preview-attachment text-truncate"
                                                        style="max-width: 220px;" title="{{ $f->original_name }}">
                                                    <i class="bx {{ $f->is_image ? 'bx-image' : 'bx-file' }}"></i> {{ $f->original_name }}
                                                </button>
                                            @endforeach
                                        </div>
                                    </div>
                                @endif
                            @endforeach
                        </div>
                    @endif
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
                            <input type="hidden" id="flaggedItems{{ $claim->id }}" name="flagged_items" value="">
                            <input type="text" id="remarksInput{{ $claim->id }}" name="remarks" class="form-control" placeholder="Enter remarks / objection reason / rejection reason..." style="border-radius: 8px;">
                            <small class="text-muted d-block mt-1" style="font-size:.72rem;">
                                <i class="bx bx-flag"></i> Tick the <strong>Flag</strong> box on any row above before raising an objection — those rows turn red on the user's form so they know exactly what to correct.
                            </small>
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
    // Carry the ticked row IDs across — the checkboxes live in the modal body,
    // outside this form, so collect them into the hidden field on submit.
    let hidden = document.getElementById('flaggedItems' + claimId);
    if (hidden) {
        hidden.value = Array.from(document.querySelectorAll('.flag-item-' + claimId + ':checked'))
                            .map(cb => cb.value).join(',');
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
