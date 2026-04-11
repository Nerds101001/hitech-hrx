{{-- Onboarding Review Modal --}}
<div class="modal fade" id="modalReviewOnboarding" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content modal-content-hitech border-0 shadow-lg" style="border-radius: 24px;">
            <!-- Fixed Header Layer -->
            <div class="modal-header-hitech-unified py-4 px-5 border-0" style="background: linear-gradient(135deg, #004d54 0%, #008080 100%); border-radius: 24px 24px 0 0; position: relative; overflow: hidden;">
                <div style="position: absolute; width: 150px; height: 150px; background: rgba(255,255,255,0.05); border-radius: 50%; top: -50px; right: -30px;"></div>
                <div class="d-flex justify-content-between align-items-center w-100 position-relative" style="z-index: 1;">
                    <div class="d-flex align-items-center">
                        <div class="modal-icon-header me-4 shadow-lg" style="background: rgba(255,255,255,0.15); backdrop-filter: blur(8px); border: 1px solid rgba(255,255,255,0.2); width: 52px; height: 52px; display: flex; align-items: center; justify-content: center; border-radius: 16px;">
                            <i class="bx bx-shield-quarter text-white fs-2"></i>
                        </div>
                        <div>
                            <h4 class="modal-title fw-extrabold mb-1 text-white" style="letter-spacing: -0.02em;">Onboarding Verification Protocol</h4>
                            <div class="d-flex align-items-center opacity-75">
                                <span class="badge bg-white bg-opacity-20 rounded-pill px-3 py-1 smallest text-white fw-bold me-2 uppercase">Compliance Check</span>
                                <p class="smallest mb-0 text-white fw-medium">Reviewing profile for <strong class="text-white">{{ $user->first_name }} {{ $user->last_name }}</strong></p>
                            </div>
                        </div>
                    </div>
                    <button type="button" class="btn-close-hitech shadow-sm" data-bs-dismiss="modal" style="background: rgba(255,255,255,0.1) !important; border: 1px solid rgba(255, 255, 255, 0.2) !important; color: white !important; width: 36px; height: 36px; border-radius: 12px; display: flex; align-items: center; justify-content: center; transition: all 0.3s ease;">
                        <i class="bx bx-x fs-4" style="color: white;"></i>
                    </button>
                </div>
            </div>

            <div class="modal-body p-0">
                <!-- Premium Horizontal Navigation -->
                <div class="hitech-nav-container">
                    <div id="stepperReviewOnboarding" class="bs-stepper shadow-none border-0 m-0">
                        <div class="bs-stepper-header p-0 border-0 hitech-nav-wrapper overflow-x-auto" style="scrollbar-width: none;">
                            <div class="step active" data-target="#step-personal">
                                <button type="button" class="step-trigger onboarding-tab-pill p-0 active border-0 bg-transparent">
                                    <i class="bx bx-chevron-left back-arrow" style="display:none; font-size: 1.2rem; margin-right: 0.4rem;"></i>
                                    <i class="bx bx-user icon-main"></i> <span>Basic Details</span>
                                </button>
                            </div>
                            <div class="tab-step-line"></div>
                            <div class="step" data-target="#step-contact">
                                <button type="button" class="step-trigger onboarding-tab-pill p-0 border-0 bg-transparent">
                                    <i class="bx bx-chevron-left back-arrow" style="display:none; font-size: 1.2rem; margin-right: 0.4rem;"></i>
                                    <i class="bx bx-map-pin icon-main"></i> <span>Contact</span>
                                </button>
                            </div>
                            <div class="tab-step-line"></div>
                            <div class="step" data-target="#step-banking">
                                <button type="button" class="step-trigger onboarding-tab-pill p-0 border-0 bg-transparent">
                                    <i class="bx bx-chevron-left back-arrow" style="display:none; font-size: 1.2rem; margin-right: 0.4rem;"></i>
                                    <i class="bx bxs-bank icon-main"></i> <span>Banking & Payroll</span>
                                </button>
                            </div>
                            <div class="tab-step-line"></div>
                            <div class="step" data-target="#step-documents">
                                <button type="button" class="step-trigger onboarding-tab-pill p-0 border-0 bg-transparent">
                                    <i class="bx bx-chevron-left back-arrow" style="display:none; font-size: 1.2rem; margin-right: 0.4rem;"></i>
                                    <i class="bx bx-file-blank icon-main"></i> <span>Documents</span>
                                </button>
                            </div>
                            <div class="tab-step-line"></div>
                            <div class="step" data-target="#step-final">
                                <button type="button" class="step-trigger onboarding-tab-pill p-0 border-0 bg-transparent">
                                    <i class="bx bx-chevron-left back-arrow" style="display:none; font-size: 1.2rem; margin-right: 0.4rem;"></i>
                                    <i class="bx bx-check-shield icon-main"></i> <span>Final Review</span>
                                </button>
                            </div>
                        </div>

                        <div class="onboarding-review-content bs-stepper-content p-5">
                            <form id="formReviewOnboarding" class="p-0 m-0">
                                @csrf
                                <!-- Step: Personal Details -->
                                <div id="step-personal" class="content active animate__animated animate__fadeIn">
                                    <div class="review-section-card">
                                        <div class="review-section-header">
                                            <div class="review-section-title">
                                                <i class="bx bx-user"></i>
                                                Primary Information
                                            </div>
                                            <div class="hitech-toggle-pill group-personal" data-section="personal">
                                                <div class="hitech-toggle-opt opt-approve active" data-value="approve">
                                                    <i class="bx bx-check-circle me-1"></i> APPROVED
                                                </div>
                                                <div class="hitech-toggle-opt opt-flag" data-value="flag">
                                                    <i class="bx bx-x-circle me-1"></i> FLAGGED
                                                </div>
                                                <input type="checkbox" class="section-reject-toggle d-none" name="sections[]" value="personal">
                                            </div>
                                        </div>
                                        <div class="p-5">
                                            <div class="mb-5" id="remarks-box-personal" style="display: none;">
                                                <label class="hitech-label-small text-danger fw-extrabold mb-2 d-block">Rejection Remarks</label>
                                                <textarea name="remarks[personal]" class="form-control border-danger bg-white p-3 shadow-none rounded-4" rows="3" placeholder="Explain what needs to be corrected..."></textarea>
                                            </div>
                                            <div class="row g-5">
                                                <div class="col-md-4">
                                                    <div class="hitech-label-small">Full Legal Name</div>
                                                    <div class="hitech-value-premium">{{ $user->first_name }} {{ $user->last_name }}</div>
                                                </div>
                                                <div class="col-md-4">
                                                    <div class="hitech-label-small">Father's Name</div>
                                                    <div class="hitech-value-premium">{{ $user->father_name ?? 'N/A' }}</div>
                                                </div>
                                                <div class="col-md-4">
                                                    <div class="hitech-label-small">Mother's Name</div>
                                                    <div class="hitech-value-premium">{{ $user->mother_name ?? 'N/A' }}</div>
                                                </div>
                                                <div class="col-md-4">
                                                    <div class="hitech-label-small">Date of Birth</div>
                                                    <div class="hitech-value-premium fw-extrabold" style="color: #008080;">{{ $user->dob ? \Carbon\Carbon::parse($user->dob)->format('d M, Y') : 'N/A' }}</div>
                                                </div>
                                                <div class="col-md-4">
                                                    <div class="hitech-label-small">Blood Group</div>
                                                    <div class="hitech-value-premium">{{ $user->blood_group ?? 'N/A' }}</div>
                                                </div>
                                                <div class="col-md-4">
                                                    <div class="hitech-label-small">Qualification</div>
                                                    <div class="hitech-value-premium text-capitalize">{{ $user->highest_qualification ?? 'N/A' }}</div>
                                                </div>
                                            </div>
                                            
                                            <div class="mt-5 p-4 border rounded-4 bg-light d-flex align-items-center justify-content-between hover-teal-light transition-all shadow-sm">
                                                <div class="d-flex align-items-center">
                                                    <div class="avatar avatar-xl me-4">
                                                        <img src="{{ $user->profile_picture ? $user->getProfilePicture() : 'https://ui-avatars.com/api/?name='.urlencode($user->name) }}" class="rounded-circle border border-4 border-white shadow-sm" style="width: 72px; height: 72px; object-fit: cover;" onerror="this.src='https://ui-avatars.com/api/?name={{ urlencode($user->name) }}&background=127464&color=fff'">
                                                    </div>
                                                    <div>
                                                        <h6 class="mb-1 fw-bold">Profile Photograph</h6>
                                                        <p class="text-muted smallest fw-medium mb-0">Official identity verification image.</p>
                                                    </div>
                                                </div>
                                                @if($user->profile_picture)
                                                    <button type="button" class="btn btn-hitech-pill-primary px-4 btn-sm shadow-sm" onclick="viewDocumentPopup('{{ $user->getProfilePicture() }}', 'Profile Photo')">
                                                        <i class="bx bx-show-alt me-1"></i> VIEW FULL IMAGE
                                                    </button>
                                                @endif
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <!-- Step: Contact Details -->
                                <div id="step-contact" class="content animate__animated animate__fadeIn">
                                    <div class="review-section-card">
                                        <div class="review-section-header">
                                            <div class="review-section-title">
                                                <i class="bx bx-map-pin"></i>
                                                Contact & Addresses
                                            </div>
                                            <div class="hitech-toggle-pill group-contact" data-section="contact">
                                                <div class="hitech-toggle-opt opt-approve active" data-value="approve">
                                                    <i class="bx bx-check-circle me-1"></i> APPROVED
                                                </div>
                                                <div class="hitech-toggle-opt opt-flag" data-value="flag">
                                                    <i class="bx bx-x-circle me-1"></i> FLAGGED
                                                </div>
                                                <input type="checkbox" class="section-reject-toggle d-none" name="sections[]" value="contact">
                                            </div>
                                        </div>
                                        <div class="p-5">
                                            <div class="mb-5" id="remarks-box-contact" style="display: none;">
                                                <label class="hitech-label-small text-danger fw-extrabold mb-2 d-block">Rejection Remarks</label>
                                                <textarea name="remarks[contact]" class="form-control border-danger bg-white p-3 shadow-none rounded-4" rows="3" placeholder="Explain why the contact details were flagged..."></textarea>
                                            </div>
                                            <div class="row g-5">
                                                <div class="col-md-6">
                                                    <div class="hitech-label-small">Work Email</div>
                                                    <div class="hitech-value-premium text-break">{{ $user->email }}</div>
                                                </div>
                                                <div class="col-md-6">
                                                    <div class="hitech-label-small">Personal Email</div>
                                                    <div class="hitech-value-premium text-break">{{ $user->personal_email ?? 'N/A' }}</div>
                                                </div>
                                                <div class="col-md-6">
                                                    <div class="hitech-label-small">Primary Phone</div>
                                                    <div class="hitech-value-premium">{{ $user->phone ?? 'N/A' }}</div>
                                                </div>
                                                <div class="col-md-6">
                                                    <div class="hitech-label-small">Emergency Contact</div>
                                                    <div class="hitech-value-premium">{{ $user->emergency_phone ?? 'N/A' }}</div>
                                                </div>
                                                <div class="col-md-12">
                                                    <div class="p-4 rounded-4 bg-light border border-dashed shadow-sm">
                                                        <div class="hitech-label-small mb-2">Permanent Address</div>
                                                        <div class="hitech-value-premium small fw-medium opacity-75">
                                                            {{ $user->perm_street }}, {{ $user->perm_city }}, {{ $user->perm_state }}, {{ $user->perm_country }} - {{ $user->perm_zip }}
                                                        </div>
                                                    </div>
                                                </div>
                                                <div class="col-md-12">
                                                    <div class="p-4 rounded-4 bg-white border shadow-sm">
                                                        <div class="hitech-label-small mb-2">Current Residence</div>
                                                        <div class="hitech-value-premium small fw-medium opacity-75">
                                                            {{ $user->temp_street }}, {{ $user->temp_city }}, {{ $user->temp_state }}, {{ $user->temp_country }} - {{ $user->temp_zip }}
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <!-- Step: Banking & Payroll -->
                                <div id="step-banking" class="content animate__animated animate__fadeIn">
                                    <div class="review-section-card">
                                        <div class="review-section-header">
                                            <div class="review-section-title">
                                                <i class="bx bxs-bank"></i>
                                                Banking & Payroll Details
                                            </div>
                                            <div class="hitech-toggle-pill group-banking" data-section="banking">
                                                <div class="hitech-toggle-opt opt-approve active" data-value="approve">
                                                    <i class="bx bx-check-circle me-1"></i> APPROVED
                                                </div>
                                                <div class="hitech-toggle-opt opt-flag" data-value="flag">
                                                    <i class="bx bx-x-circle me-1"></i> FLAGGED
                                                </div>
                                                <input type="checkbox" class="section-reject-toggle d-none" name="sections[]" value="banking">
                                            </div>
                                        </div>
                                        <div class="p-5">
                                            <div class="mb-5" id="remarks-box-banking" style="display: none;">
                                                <label class="hitech-label-small text-danger fw-extrabold mb-2 d-block">Rejection Remarks</label>
                                                <textarea name="remarks[banking]" class="form-control border-danger bg-white p-3 shadow-none rounded-4" rows="3" placeholder="Identify banking error or missing proof..."></textarea>
                                            </div>
                                            <div class="row g-5">
                                                <div class="col-md-4">
                                                    <div class="hitech-label-small">Bank Name</div>
                                                    <div class="hitech-value-premium">@if($user->bankAccount) <i class="bx bxs-institution me-1 text-primary"></i> {{ optional($user->bankAccount)->bank_name }} @else N/A @endif</div>
                                                </div>
                                                <div class="col-md-4">
                                                    <div class="hitech-label-small">Account Name</div>
                                                    <div class="hitech-value-premium text-uppercase">{{ optional($user->bankAccount)->account_name ?? 'N/A' }}</div>
                                                </div>
                                                <div class="col-md-4">
                                                    <div class="hitech-label-small">IFSC Code</div>
                                                    <div class="hitech-value-premium text-uppercase">{{ optional($user->bankAccount)->bank_code ?? 'N/A' }}</div>
                                                </div>
                                                <div class="col-md-12">
                                                    <div class="p-5 rounded-4 border-2 border-dashed border-teal text-center shadow-sm" style="background: rgba(0, 128, 128, 0.03);">
                                                        <div class="hitech-label-small text-teal mb-2 fw-extrabold">Settlement Account Number</div>
                                                        <div class="hitech-value-premium fs-2 fw-extrabold text-teal mb-4" style="letter-spacing: 2px;">{{ optional($user->bankAccount)->account_number ?? 'N/A' }}</div>
                                                        @if($user->getChequeUrl())
                                                            <button type="button" class="btn btn-hitech-pill-primary px-5 shadow-sm" onclick="viewDocumentPopup('{{ $user->getChequeUrl() }}', 'Banking Proof')">
                                                                <i class="bx bx-show me-2"></i> VIEW BANKING PROOF
                                                            </button>
                                                        @else
                                                            <span class="badge bg-label-danger py-3 px-5 rounded-pill fw-bold border border-danger">NO BANKING PROOF ATTACHED</span>
                                                        @endif
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <!-- Step: Compliance Documents -->
                                <div id="step-documents" class="content animate__animated animate__fadeIn">
                                    <div class="review-section-card">
                                        <div class="review-section-header">
                                            <div class="review-section-title">
                                                <i class="bx bx-file-blank"></i>
                                                Identity & Compliance Proofs
                                            </div>
                                            <div class="hitech-toggle-pill group-documents" data-section="documents">
                                                <div class="hitech-toggle-opt opt-approve active" data-value="approve">
                                                    <i class="bx bx-check-circle me-1"></i> APPROVED
                                                </div>
                                                <div class="hitech-toggle-opt opt-flag" data-value="flag">
                                                    <i class="bx bx-x-circle me-1"></i> FLAGGED
                                                </div>
                                                <input type="checkbox" class="section-reject-toggle d-none" name="sections[]" value="documents">
                                            </div>
                                        </div>
                                        <div class="p-5">
                                            <div class="mb-5" id="remarks-box-documents" style="display: none;">
                                                <label class="hitech-label-small text-danger fw-extrabold mb-2 d-block">Rejection Remarks</label>
                                                <textarea name="remarks[documents]" class="form-control border-danger bg-white p-3 shadow-none rounded-4" rows="3" placeholder="Specify document discrepancy..."></textarea>
                                            </div>
                                            <div class="row g-4 mb-5">
                                                <div class="col-md-6">
                                                    <div class="p-4 border rounded-4 d-flex align-items-center justify-content-between bg-white shadow-sm hover-teal-light transition-all">
                                                        <div class="d-flex align-items-center">
                                                            <div class="me-3 bg-light p-2 rounded-3" style="width: 52px; height: 52px; display: flex; align-items: center; justify-content: center;">
                                                                <i class="bx bx-id-card fs-2 text-primary"></i>
                                                            </div>
                                                            <div>
                                                                <label class="hitech-label-small mb-0">Aadhaar No.</label>
                                                                <div class="hitech-value-premium small fw-bold">{{ $user->aadhaar_no ?? 'N/A' }}</div>
                                                            </div>
                                                        </div>
                                                        @if($user->getAadhaarUrl())
                                                            <button type="button" class="btn btn-hitech-pill-primary btn-xs px-3" onclick="viewDocumentPopup('{{ $user->getAadhaarUrl() }}', 'Aadhaar Card')">PREVIEW</button>
                                                        @endif
                                                    </div>
                                                </div>
                                                <div class="col-md-6">
                                                    <div class="p-4 border rounded-4 d-flex align-items-center justify-content-between bg-white shadow-sm hover-teal-light transition-all">
                                                        <div class="d-flex align-items-center">
                                                            <div class="me-3 bg-light p-2 rounded-3" style="width: 52px; height: 52px; display: flex; align-items: center; justify-content: center;">
                                                                <i class="bx bx-credit-card-front fs-2 text-info"></i>
                                                            </div>
                                                            <div>
                                                                <label class="hitech-label-small mb-0">PAN No.</label>
                                                                <div class="hitech-value-premium small fw-bold uppercase">{{ $user->pan_no ?? 'N/A' }}</div>
                                                            </div>
                                                        </div>
                                                        @if($user->getPanUrl())
                                                            <button type="button" class="btn btn-hitech-pill-primary btn-xs px-3" onclick="viewDocumentPopup('{{ $user->getPanUrl() }}', 'PAN Card')">PREVIEW</button>
                                                        @endif
                                                    </div>
                                                </div>
                                            </div>

                                            <div class="card border border-light shadow-none rounded-4 overflow-hidden shadow-sm">
                                                <div class="table-responsive">
                                                    <table class="table table-hover align-middle mb-0">
                                                        <thead>
                                                            <tr class="bg-light">
                                                                <th class="ps-4 py-3 smallest fw-extrabold uppercase text-muted">Certification</th>
                                                                <th class="py-3 smallest fw-extrabold uppercase text-muted">Authority</th>
                                                                <th class="py-3 smallest fw-extrabold uppercase text-muted text-center">Reference No.</th>
                                                                <th class="pe-4 py-3 smallest fw-extrabold uppercase text-muted text-center">Attachment</th>
                                                            </tr>
                                                        </thead>
                                                        <tbody>
                                                            <tr>
                                                                <td class="ps-4 fw-bold text-dark">Matriculation (10th)</td>
                                                                <td class="small fw-medium">{{ $user->matric_university ?? 'N/A' }}</td>
                                                                <td class="small font-monospace text-muted text-center">{{ $user->matric_marksheet_no ?? 'N/A' }}</td>
                                                                <td class="pe-4 text-center">
                                                                    @if($user->getMatricUrl())
                                                                        <button type="button" class="btn btn-hitech-pill-outline btn-xs px-3" onclick="viewDocumentPopup('{{ $user->getMatricUrl() }}', 'Matric Certificate', '{{ $user->matric_marksheet_no }}')">View Doc</button>
                                                                    @else
                                                                        <span class="badge bg-label-secondary badge-xs">Not Filed</span>
                                                                    @endif
                                                                </td>
                                                            </tr>
                                                            <tr class="border-top">
                                                                <td class="ps-4 fw-bold text-dark">Intermediate (12th)</td>
                                                                <td class="small fw-medium">{{ $user->inter_university ?? 'N/A' }}</td>
                                                                <td class="small font-monospace text-muted text-center">{{ $user->inter_marksheet_no ?? 'N/A' }}</td>
                                                                <td class="pe-4 text-center">
                                                                    @if($user->getInterUrl())
                                                                        <button type="button" class="btn btn-hitech-pill-outline btn-xs px-3" onclick="viewDocumentPopup('{{ $user->getInterUrl() }}', 'Intermediate Certificate')">View Doc</button>
                                                                    @else
                                                                        <span class="badge bg-label-secondary badge-xs">Not Filed</span>
                                                                    @endif
                                                                </td>
                                                            </tr>
                                                            @if($user->bachelor_university)
                                                            <tr class="border-top">
                                                                <td class="ps-4 fw-bold text-dark">Bachelor Degree</td>
                                                                <td class="small fw-medium">{{ $user->bachelor_university ?? 'N/A' }}</td>
                                                                <td class="small font-monospace text-muted text-center">{{ $user->bachelor_marksheet_no ?? 'N/A' }}</td>
                                                                <td class="pe-4 text-center">
                                                                    @if($user->getBachelorUrl())
                                                                        <button type="button" class="btn btn-hitech-pill-outline btn-xs px-3" onclick="viewDocumentPopup('{{ $user->getBachelorUrl() }}', 'Bachelor Certificate')">View Doc</button>
                                                                    @else
                                                                        <span class="badge bg-label-secondary badge-xs">Not Filed</span>
                                                                    @endif
                                                                </td>
                                                            </tr>
                                                            @endif
                                                        </tbody>
                                                    </table>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <!-- Step: Final Decision -->
                                <div id="step-final" class="content animate__animated animate__fadeIn">
                                    <div class="review-section-card shadow-sm">
                                        <div class="review-section-header">
                                            <div class="review-section-title">
                                                <i class="bx bx-check-shield"></i>
                                                Outcome Declaration
                                            </div>
                                        </div>
                                        <div class="p-5">
                                            <div class="p-5 rounded-4 mb-5 shadow-sm" style="background: #f8fafc; border: 1px solid #edf2f7;">
                                                <h6 class="fw-extrabold mb-4 text-dark d-flex align-items-center">
                                                    <i class="bx bx-message-square-dots me-2 text-teal fs-4"></i> 
                                                    VERIFICATION SUMMARY & AUDITOR FEEDBACK
                                                </h6>
                                                <textarea class="form-control bg-white shadow-none border p-4 rounded-4 fw-medium text-dark" id="reviewNotes" name="notes" rows="6" style="font-size: 0.95rem; line-height: 1.6;" placeholder="Describe your findings. If rejecting, provide clear corrective instructions..."></textarea>
                                                <div class="mt-4 p-4 rounded-3 bg-white border d-flex align-items-start">
                                                    <i class="bx bx-info-circle me-3 text-teal fs-4 mt-1"></i>
                                                    <div class="small text-muted fw-medium lh-base">
                                                        <strong>Audit Protocol:</strong> Approval will move the employee to the <strong>ACTIVE</strong> production set. Rejection will trigger a <strong>Correction Workflow</strong> notification.
                                                    </div>
                                                </div>
                                            </div>
                                            
                                            <div class="mt-5 p-5 bg-white rounded-5 border-2 border-dashed border-light d-flex align-items-center justify-content-center shadow-none" id="decisionLockInfo" style="min-height: 200px;">
                                                {{-- Decision state content injected via Javascript --}}
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </form>
                        </div> <!-- End bs-stepper-content -->
                    </div> <!-- End stepper root -->
                </div> <!-- End hitech-nav-container -->
            </div> <!-- End modal-body -->

            <div class="modal-footer px-5 py-4 bg-white border-top border-light shadow-lg">
                <div class="w-100 d-flex justify-content-between align-items-center">
                    <div class="d-flex align-items-center gap-3">
                        <button type="button" class="btn btn-hitech-pill-secondary px-5 fw-bold" data-bs-dismiss="modal">EXIT PROTOCOL</button>
                        <button type="button" class="btn btn-hitech-pill-outline px-4 btn-prev shadow-none fw-bold" style="display: none; border: 1.5px solid #e2e8f0 !important; color: #475569 !important;">
                            <i class="bx bx-chevron-left me-1"></i> BACK
                        </button>
                    </div>
                    <div class="d-flex gap-3">
                        <button type="button" class="btn btn-hitech-pill-primary px-5 shadow-sm btn-next fw-bold" style="min-width: 160px; height: 50px;">
                            PROCEED <i class="bx bx-chevron-right ms-1"></i>
                        </button>
                        
                        <button type="button" class="btn btn-hitech-pill-danger px-5 shadow-sm fw-bold animate__animated animate__shakeX" id="btnSendModification" 
                            onclick="submitReviewModification()" 
                            style="display: none; min-width: 220px; height: 50px;">
                            <i class="bx bx-paper-plane me-2"></i> SEND CORRECTION
                        </button>
                        
                        <button type="button" class="btn btn-hitech-pill-success px-5 shadow-lg animate__animated animate__pulse animate__infinite fw-bold" id="btnApproveAndActivate" 
                            onclick="approveOnboarding({{ $user->id }})" 
                            style="display: none; min-width: 240px; height: 50px; background: linear-gradient(135deg, #059669 0%, #10b981 100%) !important;">
                            <i class="bx bx-check-double me-2"></i> APPROVE & ACTIVATE
                        </button>
                    </div>
                </div>
            </div>

        </div>
    </div>
</div>
