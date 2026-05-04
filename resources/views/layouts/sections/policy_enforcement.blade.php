@if(isset($globalPendingPolicies) && $globalPendingPolicies->isNotEmpty())
    @php
        $currentPolicy = $globalPendingPolicies->first();
    @endphp

    <!-- SweetAlert2 for notifications -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

    <div class="modal fade" id="forcePolicyModal" data-bs-backdrop="static" data-bs-keyboard="false" tabindex="-1">
        <div class="modal-dialog modal-xl modal-dialog-centered">
            <div class="modal-content" style="height: 85vh;">
                <div class="modal-header bg-danger">
                    <h5 class="modal-title text-white">
                        <i class="bx bx-error-circle me-2"></i> Mandatory Company Policy
                    </h5>
                    <div class="text-white small">
                        Pending: {{ $globalPendingPolicies->count() }}
                    </div>
                </div>
                <div class="modal-body p-0 d-flex flex-column">
                    <div class="p-3 bg-lighter border-bottom">
                        <strong>{{ $currentPolicy->title }}</strong>: Please scroll through and read the policy below. You must acknowledge it to proceed.
                    </div>
                    
                    <!-- PDF Viewer -->
                    <div class="flex-grow-1 bg-dark">
                        <iframe id="policyIframe" src="" width="100%" height="100%" style="border: none;"></iframe>
                    </div>

                    <!-- Footer Controls -->
                    <div class="p-3 bg-white border-top">
                        <div class="form-check mb-3">
                            <input class="form-check-input" type="checkbox" id="forceConfirmCheckbox">
                            <label class="form-check-label fw-bold" for="forceConfirmCheckbox">
                                I, {{ auth()->user()->name }}, have read and understood the policy.
                            </label>
                        </div>
                        <div class="modal-footer border-0 p-0">
                            <button type="button" id="forceSignBtn" class="btn btn-primary w-100 py-3 fw-bold shadow-sm" disabled>
                                <i class="bx bx-check-double me-1"></i> I Have Read & Acknowledge This Policy
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
    document.addEventListener('DOMContentLoaded', function() {
        // Wait for Swal to load if it's slow
        const checkSwal = setInterval(() => {
            if (typeof Swal !== 'undefined') {
                clearInterval(checkSwal);
                initModal();
            }
        }, 100);

        function initModal() {
            const modalEl = document.getElementById('forcePolicyModal');
            
            modalEl.addEventListener('shown.bs.modal', function () {
                // Persistent cleanup for 3 seconds to beat any race conditions
                let count = 0;
                const interval = setInterval(() => {
                    document.querySelectorAll('[aria-hidden="true"]').forEach(el => {
                        el.removeAttribute('aria-hidden');
                    });
                    count++;
                    if (count > 6) clearInterval(interval);
                }, 500);
            });

            const modal = new bootstrap.Modal(modalEl);
            const policyId = '{{ $currentPolicy->id }}';
            const iframe = document.getElementById('policyIframe');
            const signBtn = document.getElementById('forceSignBtn');
            const checkbox = document.getElementById('forceConfirmCheckbox');

            modal.show();

            // Load PDF URL
            fetch(`/hr-policies/embed-url/${policyId}`)
                .then(response => response.json())
                .then(data => {
                    if(data.url) iframe.src = data.url;
                });

            checkbox.addEventListener('change', function() {
                signBtn.disabled = !this.checked;
            });

            signBtn.addEventListener('click', function() {
                const btn = this;
                btn.disabled = true;
                btn.innerHTML = '<i class="bx bx-loader-alt bx-spin me-1"></i> Acknowledging...';

                $.ajax({
                    url: `/hr-policies/acknowledge/${policyId}`,
                    method: 'POST',
                    data: { _token: '{{ csrf_token() }}' },
                    success: function(response) {
                        // Close modal and show a quick toast instead of a modal alert
                        const modalEl = document.getElementById('forcePolicyModal');
                        const modal = bootstrap.Modal.getInstance(modalEl);
                        if (modal) modal.hide();

                        // Use a simple toast or just reload immediately for maximum speed
                        location.reload();
                    },
                    error: function(xhr) {
                        btn.disabled = false;
                        btn.innerHTML = '<i class="bx bx-check-double me-1"></i> I Have Read & Acknowledge This Policy';
                        Swal.fire({
                            icon: 'error',
                            title: 'Error',
                            text: xhr.responseJSON ? xhr.responseJSON.error : 'Server Error (500). Please check storage/logs.'
                        });
                    }
                });
            });
        }
    });
    </script>
    <style>
        .modal-xl { max-width: 80% !important; }
        @media (min-width: 1200px) {
            .modal-xl { max-width: 70% !important; }
        }
    </style>
@endif
