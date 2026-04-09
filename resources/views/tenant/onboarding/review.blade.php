@extends('layouts/layoutMaster')

@section('title', 'Review Onboarding')

@section('vendor-style')
    @vite([
        'resources/assets/vendor/scss/pages/hitech-portal.scss'
    ])
    <style>
        .review-card {
            background: rgba(255, 255, 255, 0.8);
            backdrop-filter: blur(15px);
            border: 1px solid rgba(255, 255, 255, 0.5);
            border-radius: 20px;
        }
        .info-label {
            font-size: 0.8rem;
            color: #718096;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            font-weight: 600;
        }
        .info-value {
            font-size: 1rem;
            color: #2d3748;
            font-weight: 500;
        }
        .document-preview-card {
            background: #f8fafc;
            border: 1px solid #e2e8f0;
            border-radius: 12px;
            padding: 1rem;
            display: flex;
            align-items: center;
            gap: 1rem;
            transition: all 0.2s;
        }
        .document-preview-card:hover {
            border-color: #008080;
            background: #fff;
            box-shadow: 0 5px 15px rgba(0, 128, 128, 0.05);
        }
    </style>
@endsection

@section('content')
<div class="container-xxl flex-grow-1 container-p-y">
    <div class="d-flex justify-content-between align-items-center mb-6">
        <div>
            <h3 class="fw-bold text-heading mb-1">Onboarding Review</h3>
            <p class="text-muted mb-0">Review submission for <strong>{{ $user->getFullName() }}</strong></p>
        </div>
        <div class="d-flex gap-3">
            <button type="button" class="btn btn-label-danger px-6" data-bs-toggle="modal" data-bs-target="#resubmissionModal">
                <i class="bx bx-revision me-2"></i> Request Resubmission
            </button>
            <form action="{{ route('employees.onboarding.approve', $user->id) }}" method="POST">
                @csrf
                <button type="submit" class="btn btn-hitech-primary px-8">
                    <i class="bx bx-check-circle me-2"></i> Approve Onboarding
                </button>
            </form>
        </div>
    </div>

    <div class="row g-6">
        <!-- Left Column: Details -->
        <div class="col-lg-8">
            <!-- Personal Info Card -->
            <div class="review-card p-6 mb-6">
                <div class="d-flex align-items-center gap-3 mb-6">
                    <div class="avatar avatar-lg">
                        @php $profilePic = $user->getProfilePicture(); @endphp
                        <img src="{{ $profilePic ?: asset('assets/img/avatars/1.png') }}" class="rounded-circle">
                    </div>
                    <div>
                        <h4 class="mb-0 fw-bold">{{ $user->getFullName() }}</h4>
                        <span class="badge bg-label-info">{{ $user->roles->first()->display_name ?? 'N/A' }}</span>
                    </div>
                </div>

                <h5 class="fw-bold text-teal mb-4"><i class="bx bx-user me-2"></i> Personal Details</h5>
                <div class="row g-4 mb-8">
                    <div class="col-md-4">
                        <label class="info-label">Father's Name</label>
                        <div class="info-value">{{ $user->father_name ?? 'N/A' }}</div>
                    </div>
                    <div class="col-md-4">
                        <label class="info-label">Mother's Name</label>
                        <div class="info-value">{{ $user->mother_name ?? 'N/A' }}</div>
                    </div>
                    <div class="col-md-4">
                        <label class="info-label">DOB / Gender</label>
                        <div class="info-value">{{ $user->dob ? \Carbon\Carbon::parse($user->dob)->format('d M, Y') : 'N/A' }} ({{ ucfirst($user->gender ?? 'N/A') }})</div>
                    </div>
                    <div class="col-md-4">
                        <label class="info-label">Marital Status</label>
                        <div class="info-value">{{ ucfirst($user->marital_status ?? 'N/A') }}</div>
                    </div>
                    <div class="col-md-4">
                        <label class="info-label">Blood Group</label>
                        <div class="info-value">{{ $user->blood_group ?? 'N/A' }}</div>
                    </div>
                    <div class="col-md-4">
                        <label class="info-label">Contact</label>
                        <div class="info-value">{{ $user->phone }} / {{ $user->email }}</div>
                    </div>
                </div>

                <h5 class="fw-bold text-teal mb-4"><i class="bx bx-wallet me-2"></i> Bank Details</h5>
                <div class="row g-4 mb-8">
                    <div class="col-md-6">
                        <label class="info-label">Bank Name</label>
                        <div class="info-value">{{ $bank->bank_name ?? 'N/A' }}</div>
                    </div>
                    <div class="col-md-6">
                        <label class="info-label">Account Name</label>
                        <div class="info-value">{{ $bank->account_name ?? 'N/A' }}</div>
                    </div>
                    <div class="col-md-6">
                        <label class="info-label">Account Number</label>
                        <div class="info-value font-monospace">{{ $bank->account_number ?? 'N/A' }}</div>
                    </div>
                    <div class="col-md-6">
                        <label class="info-label">IFSC Code</label>
                        <div class="info-value font-monospace text-uppercase">{{ $bank->bank_code ?? 'N/A' }}</div>
                    </div>
                </div>

                <h5 class="fw-bold text-teal mb-4"><i class="bx bx-file me-2"></i> Documents Submitted</h5>
                <div class="row g-4">
                    @php
                        $onboardingFolder = \Constants::BaseFolderOnboardingDocuments . $user->id;
                        $files = Storage::disk('public')->files($onboardingFolder);
                    @endphp
                    @forelse($files as $file)
                        <div class="col-md-6">
                            <a href="{{ \App\Helpers\FileSecurityHelper::generateSecureUrl($file) }}" target="_blank" class="document-preview-card">
                                <i class="bx bx-file fs-2 text-warning"></i>
                                <div>
                                    <div class="fw-bold text-heading small">{{ basename($file) }}</div>
                                    <div class="small text-muted">Click to view</div>
                                </div>
                            </a>
                        </div>
                    @empty
                        <div class="col-12 text-center py-4 bg-light rounded">
                            <p class="text-muted mb-0">No documents found in storage.</p>
                        </div>
                    @endforelse
                </div>
            </div>
        </div>

        <!-- Right Column: Meta & Actions -->
        <div class="col-lg-4">
            <div class="review-card p-6 mb-6">
                <h5 class="fw-bold mb-4">Submission Meta</h5>
                <div class="d-flex flex-column gap-3">
                    <div class="d-flex justify-content-between small">
                        <span class="text-muted">Invite Sent:</span>
                        <span class="fw-bold">{{ $user->onboarding_at ? \Carbon\Carbon::parse($user->onboarding_at)->format('d M, Y') : 'N/A' }}</span>
                    </div>
                    <div class="d-flex justify-content-between small">
                        <span class="text-muted">Submitted on:</span>
                        <span class="fw-bold">{{ $user->onboarding_completed_at ? \Carbon\Carbon::parse($user->onboarding_completed_at)->format('d M, Y') : 'N/A' }}</span>
                    </div>
                    <div class="d-flex justify-content-between small">
                        <span class="text-muted">Deadline:</span>
                        <span class="fw-bold {{ \Carbon\Carbon::parse($user->onboarding_deadline)->isPast() ? 'text-danger' : 'text-success' }}">
                            {{ \Carbon\Carbon::parse($user->onboarding_deadline)->format('d M, Y') }}
                        </span>
                    </div>
                </div>
                <hr class="my-6">
                <div class="alert bg-label-warning small mb-0">
                    <i class="bx bx-info-circle me-1"></i> Approving this will unlock the full dashboard for the user and send a confirmation email.
                </div>
            </div>
            
            @if($user->onboarding_resubmission_notes)
                <div class="review-card p-6 border-danger">
                    <h5 class="fw-bold text-danger mb-3">Previous Resubmission Note</h5>
                    <p class="small text-muted mb-0 italic">"{{ $user->onboarding_resubmission_notes }}"</p>
                </div>
            @endif
        </div>
    </div>
</div>

<!-- Resubmission Modal -->
<div class="modal fade" id="resubmissionModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content modal-content-hitech">
            <div class="modal-header modal-header-hitech">
              <div class="d-flex align-items-center">
                  <div class="modal-icon-header me-3">
                      <i class="bx bx-briefcase"></i>
                  </div>
                  <h5 class="modal-title modal-title-hitech mb-0">Request Resubmission</h5>
              </div>
              <button type="button" class="btn-close-hitech" data-bs-dismiss="modal">
                <i class="bx bx-x"></i>
              </button>
            </div>
            <form action="{{ route('employees.onboarding.resubmit', $user->id) }}" method="POST">
                @csrf
                <div class="modal-body modal-body-hitech pb-0">
                    <label class="form-label-hitech">What needs to be corrected? <span class="text-danger">*</span></label>
                    <textarea name="notes" class="form-control form-control-hitech" rows="5" placeholder="e.g. Please upload a clearer copy of your PAN card and verify the bank account number." required style="resize: none;"></textarea>
                    <div class="mt-4 p-4 rounded bg-light border-start border-warning border-4">
                        <p class="small text-muted mb-0">
                            <i class="bx bx-info-circle me-1"></i> This note will be sent to the user via email and their onboarding form will be unlocked for editing.
                        </p>
                    </div>
                </div>
                <div class="modal-footer modal-footer-hitech border-0 d-flex justify-content-end gap-3 pt-4">
                    <button type="button" class="btn btn-label-secondary px-6" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-hitech-primary px-8">
                        Send Request <i class="bx bx-paper-plane ms-1"></i>
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection
