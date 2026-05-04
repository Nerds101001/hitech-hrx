@extends('layouts/layoutMaster')

@section('title', 'Company Policies')

@section('content')
<h4 class="py-3 mb-4">
    <span class="text-muted fw-light">Compliance /</span> Company Policies
</h4>

<div class="row">
    @forelse($policies as $policy)
        @php
            $isAcknowledged = in_array($policy->id, $acknowledgedIds);
        @endphp
        <div class="col-md-6 col-lg-4 mb-4">
            <div class="card h-100 {{ !$isAcknowledged && $policy->is_mandatory ? 'border-danger' : '' }}">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-start mb-2">
                        <span class="badge bg-label-primary">{{ $policy->category }}</span>
                        @if($isAcknowledged)
                            <span class="badge bg-success"><i class="bx bx-check-circle me-1"></i> Acknowledged</span>
                        @elseif($policy->is_mandatory)
                            <span class="badge bg-danger">Mandatory</span>
                        @endif
                    </div>
                    <h5 class="card-title mt-3">{{ $policy->title }}</h5>
                    <p class="card-text text-muted small">
                        {{ \Illuminate\Support\Str::limit($policy->description, 100) }}
                    </p>
                    
                    <div class="mt-4 d-flex gap-2">
                        <a href="{{ route('hr-policies.view', $policy->id) }}" target="_blank" class="btn btn-outline-primary btn-sm flex-grow-1">
                            <i class="bx bx-show me-1"></i> View PDF
                        </a>
                        @if(!$isAcknowledged)
                            <button type="button" class="btn btn-primary btn-sm flex-grow-1 acknowledge-btn" data-id="{{ $policy->id }}" data-title="{{ $policy->title }}">
                                <i class="bx bx-edit-alt me-1"></i> Acknowledge
                            </button>
                        @endif
                    </div>
                </div>
            </div>
        </div>
    @empty
        <div class="col-12">
            <div class="card bg-lighter">
                <div class="card-body text-center py-5">
                    <i class="bx bx-file-blank display-1 text-muted mb-3"></i>
                    <h5>No policies found.</h5>
                    <p class="text-muted">You are up to date with all company policies.</p>
                </div>
            </div>
        </div>
    @endforelse
</div>

<!-- Acknowledgment Modal -->
<div class="modal fade" id="acknowledgeModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Confirm Acknowledgment</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <p>By clicking "Sign & Confirm", you acknowledge that you have read and understood the policy: <strong id="modalPolicyTitle"></strong>.</p>
                <p class="text-muted small">A digitally signed copy of this acknowledgment will be generated and sent to your email ID.</p>
                
                <div class="form-check mt-3">
                    <input class="form-check-input" type="checkbox" id="confirmCheckbox">
                    <label class="form-check-label" for="confirmCheckbox">
                        I confirm that I have read the policy in full.
                    </label>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-label-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-primary" id="confirmAcknowledgeBtn" disabled>
                    <span class="spinner-border spinner-border-sm d-none me-1" role="status"></span>
                    Sign & Confirm
                </button>
            </div>
        </div>
    </div>
</div>
@endsection

@section('page-script')
<!-- SweetAlert2 -->
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css">
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

<script>
document.addEventListener('DOMContentLoaded', function() {
    $(function() {
        let selectedPolicyId = null;

    $('.acknowledge-btn').on('click', function() {
        selectedPolicyId = $(this).data('id');
        $('#modalPolicyTitle').text($(this).data('title'));
        $('#confirmCheckbox').prop('checked', false);
        $('#confirmAcknowledgeBtn').prop('disabled', true);
        $('#acknowledgeModal').modal('show');
    });

    $('#confirmCheckbox').on('change', function() {
        $('#confirmAcknowledgeBtn').prop('disabled', !$(this).is(':checked'));
    });

    $('#confirmAcknowledgeBtn').on('click', function() {
        const btn = $(this);
        const spinner = btn.find('.spinner-border');
        
        btn.prop('disabled', true);
        spinner.removeClass('d-none');

        $.ajax({
            url: `/hr-policies/acknowledge/${selectedPolicyId}`,
            method: 'POST',
            data: {
                _token: '{{ csrf_token() }}'
            },
            success: function(response) {
                // Instant reload for speed
                location.reload();
            },
            error: function(xhr) {
                btn.prop('disabled', false);
                spinner.addClass('d-none');
                let msg = 'Something went wrong.';
                if(xhr.responseJSON && xhr.responseJSON.error) msg = xhr.responseJSON.error;
                
                Swal.fire({
                    icon: 'error',
                    title: 'Error',
                    text: msg
                });
            }
        });
        });
    });
});
</script>
@endsection
