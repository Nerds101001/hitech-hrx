@extends('layouts/layoutMaster')

@section('title', 'Pending Approvals')

@section('vendor-style')
  @vite([
    'resources/assets/vendor/libs/datatables-bs5/datatables.bootstrap5.scss',
    'resources/assets/vendor/libs/datatables-responsive-bs5/responsive.bootstrap5.scss',
    'resources/assets/vendor/libs/animate-css/animate.scss',
    'resources/assets/vendor/libs/sweetalert2/sweetalert2.scss',
    'resources/assets/vendor/scss/pages/hitech-portal.scss'
  ])
@endsection

@section('content')
<div class="container-xxl flex-grow-1 container-p-y animate__animated animate__fadeIn">
    <!-- Hero Section -->
    <div class="hitech-page-hero mb-6">
        <div class="hitech-page-hero-text">
            <h4 class="greeting">Pending Approvals</h4>
            <p class="sub-text">Review and manage profile updates and document verifications.</p>
        </div>
        <div class="emp-hero-meta">
            <div class="hero-quick-stat">
                <div class="stat-value">{{ $profileApprovals->count() + $documentApprovals->count() }}</div>
                <div class="stat-label">Total Pending</div>
            </div>
        </div>
    </div>

    <div class="row">
        <div class="col-12">
            <div class="nav-align-top mb-4">
                <ul class="nav nav-pills mb-4 gap-2" role="tablist">
                    <li class="nav-item">
                        <button type="button" class="nav-link active rounded-pill px-4 fw-bold" role="tab" data-bs-toggle="tab" data-bs-target="#navs-pills-top-profile">
                            <i class="bx bx-user me-2"></i> Profile & Bank Updates
                            @if($profileApprovals->count() > 0)
                                <span class="badge rounded-pill bg-danger ms-2">{{ $profileApprovals->count() }}</span>
                            @endif
                        </button>
                    </li>
                    <li class="nav-item">
                        <button type="button" class="nav-link rounded-pill px-4 fw-bold" role="tab" data-bs-toggle="tab" data-bs-target="#navs-pills-top-documents">
                            <i class="bx bx-file me-2"></i> Document Verification
                            @if($documentApprovals->count() > 0)
                                <span class="badge rounded-pill bg-danger ms-2">{{ $documentApprovals->count() }}</span>
                            @endif
                        </button>
                    </li>
                </ul>
                <div class="tab-content p-0 bg-transparent shadow-none">
                    {{-- Profile & Bank Tab --}}
                    <div class="tab-pane fade show active" id="navs-pills-top-profile" role="tabpanel">
                        <div class="hitech-card-glass p-5">
                            <div class="table-responsive">
                                <table class="table table-hover align-middle">
                                    <thead>
                                        <tr>
                                            <th>Employee</th>
                                            <th>Update Type</th>
                                            <th>Requested Data</th>
                                            <th>Date Submitted</th>
                                            <th class="text-center">Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @forelse($profileApprovals as $approval)
                                            <tr>
                                                <td>
                                                    <div class="d-flex align-items-center">
                                                        <div class="avatar avatar-md me-3">
                                                            @if($approval->user->profile_picture)
                                                                <img src="{{ $approval->user->getProfilePicture() }}" alt="Avatar" class="rounded-circle border border-2 border-white shadow-sm">
                                                            @else
                                                                <span class="avatar-initial rounded-circle bg-label-primary shadow-sm">{{ $approval->user->getInitials() }}</span>
                                                            @endif
                                                        </div>
                                                        <div>
                                                            <h6 class="mb-0 fw-bold">{{ $approval->user->full_name }}</h6>
                                                            <small class="text-muted">{{ $approval->user->code }}</small>
                                                        </div>
                                                    </div>
                                                </td>
                                                <td>
                                                    <span class="badge-hitech bg-label-info text-capitalize">{{ str_replace('_', ' ', $approval->type) }}</span>
                                                </td>
                                                <td>
                                                    <button class="btn btn-sm btn-hitech-secondary rounded-pill px-3" type="button" data-bs-toggle="collapse" data-bs-target="#data-{{ $approval->id }}">
                                                        <i class="bx bx-show-alt me-1"></i> View Details
                                                    </button>
                                                    <div class="collapse mt-2" id="data-{{ $approval->id }}">
                                                        <div class="p-4 bg-light rounded-4 border shadow-xs small">
                                                            @foreach($approval->requested_data as $key => $value)
                                                                @if($value)
                                                                    <div class="mb-2 d-flex justify-content-between border-bottom pb-1 border-white">
                                                                        <strong class="text-capitalize text-muted small">{{ str_replace('_', ' ', $key) }}:</strong> 
                                                                        <span class="fw-bold text-dark">{{ $value }}</span>
                                                                    </div>
                                                                @endif
                                                            @endforeach
                                                        </div>
                                                    </div>
                                                </td>
                                                <td><span class="text-muted small fw-medium">{{ $approval->created_at->diffForHumans() }}</span></td>
                                                <td>
                                                    <div class="d-flex justify-content-center gap-2">
                                                        <form action="{{ route('approvals.profile.approve', $approval->id) }}" method="POST">
                                                            @csrf
                                                            <button type="submit" class="btn btn-sm btn-hitech rounded-pill px-4">Approve</button>
                                                        </form>
                                                        <button type="button" class="btn btn-sm btn-hitech-danger rounded-pill px-4" data-bs-toggle="modal" data-bs-target="#rejectModal" data-type="profile" data-id="{{ $approval->id }}">Reject</button>
                                                    </div>
                                                </td>
                                            </tr>
                                        @empty
                                            <tr>
                                                <td colspan="5" class="text-center py-5 text-muted">
                                                    <i class="bx bx-check-circle fs-1 d-block mb-3 opacity-25"></i>
                                                    No pending profile or bank updates.
                                                </td>
                                            </tr>
                                        @endforelse
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>

                    {{-- Document Tab --}}
                    <div class="tab-pane fade" id="navs-pills-top-documents" role="tabpanel">
                        <div class="hitech-card-glass p-5">
                            <div class="table-responsive">
                                <table class="table table-hover align-middle">
                                    <thead>
                                        <tr>
                                            <th>Employee</th>
                                            <th>Document Type</th>
                                            <th>Ref Number / Remarks</th>
                                            <th>File</th>
                                            <th>Date Submitted</th>
                                            <th class="text-center">Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @forelse($documentApprovals as $doc)
                                            <tr>
                                                <td>
                                                    <div class="d-flex align-items-center">
                                                        <div class="avatar avatar-md me-3">
                                                            @if($doc->user->profile_picture)
                                                                <img src="{{ $doc->user->getProfilePicture() }}" alt="Avatar" class="rounded-circle border border-2 border-white shadow-sm">
                                                            @else
                                                                <span class="avatar-initial rounded-circle bg-label-primary shadow-sm">{{ $doc->user->getInitials() }}</span>
                                                            @endif
                                                        </div>
                                                        <div>
                                                            <h6 class="mb-0 fw-bold">{{ $doc->user->full_name }}</h6>
                                                            <small class="text-muted">{{ $doc->user->code }}</small>
                                                        </div>
                                                    </div>
                                                </td>
                                                <td><span class="badge-hitech bg-label-primary">{{ $doc->documentType->name }}</span></td>
                                                <td><span class="fw-medium">{{ $doc->remarks ?: '-' }}</span></td>
                                                <td>
                                                    <a href="{{ route('auth.document.serve', ['path' => base64_encode($doc->generated_file)]) }}" target="_blank" class="btn btn-sm btn-hitech-secondary rounded-pill px-3">
                                                        <i class="bx bx-show me-1"></i> View File
                                                    </a>
                                                </td>
                                                <td><span class="text-muted small fw-medium">{{ $doc->created_at->diffForHumans() }}</span></td>
                                                <td>
                                                    <div class="d-flex justify-content-center gap-2">
                                                        <form action="{{ route('approvals.document.approve', $doc->id) }}" method="POST">
                                                            @csrf
                                                            <button type="submit" class="btn btn-sm btn-hitech rounded-pill px-4">Approve</button>
                                                        </form>
                                                        <button type="button" class="btn btn-sm btn-hitech-danger rounded-pill px-4" data-bs-toggle="modal" data-bs-target="#rejectModal" data-type="document" data-id="{{ $doc->id }}">Reject</button>
                                                    </div>
                                                </td>
                                            </tr>
                                        @empty
                                            <tr>
                                                <td colspan="6" class="text-center py-5 text-muted">
                                                    <i class="bx bx-file fs-1 d-block mb-3 opacity-25"></i>
                                                    No pending document verifications.
                                                </td>
                                            </tr>
                                        @endforelse
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

{{-- Reject Modal --}}
<div class="modal fade animate__animated animate__fadeInUp" id="rejectModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <form id="rejectForm" method="POST" class="modal-content border-0 shadow-lg" style="border-radius: 20px;">
            @csrf
            <div class="modal-header bg-light border-0 py-3">
                <h5 class="modal-title fw-bold text-danger">Reject Request</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body p-4">
                <div class="mb-3">
                    <label class="form-label small fw-bold" for="remarks">Reason for Rejection</label>
                    <textarea class="form-control rounded-3" name="remarks" id="remarks" rows="3" required placeholder="Provide a reason for the employee..."></textarea>
                </div>
            </div>
            <div class="modal-footer border-0 bg-light py-3">
                <button type="button" class="btn btn-label-secondary px-4 rounded-pill" data-bs-dismiss="modal">Cancel</button>
                <button type="submit" class="btn btn-danger px-4 rounded-pill shadow-sm">Confirm Rejection</button>
            </div>
        </form>
    </div>
</div>

@section('page-script')
<script>
    document.addEventListener('DOMContentLoaded', function() {
        const rejectModal = document.getElementById('rejectModal');
        rejectModal.addEventListener('show.bs.modal', function(event) {
            const button = event.relatedTarget;
            const type = button.getAttribute('data-type');
            const id = button.getAttribute('data-id');
            const form = document.getElementById('rejectForm');
            
            if (type === 'profile') {
                form.action = `/approvals/profile/${id}/reject`;
            } else {
                form.action = `/approvals/document/${id}/reject`;
            }
        });
    });
</script>
@endsection
@endsection
