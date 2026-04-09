<div class="modal fade" id="modalLeaveRequestDetails" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content modal-content-hitech">
            <!-- Premium HITECH Header -->
            <div class="modal-header modal-header-hitech">
                <div class="d-flex align-items-center">
                    <div class="modal-icon-header me-3">
                        <i class="bx bx-file-blank fs-3"></i>
                    </div>
                    <h5 class="modal-title modal-title-hitech">Review Leave: <span id="userNameHeader"></span></h5>
                </div>
                <button type="button" class="btn-close-hitech" data-bs-dismiss="modal" aria-label="Close">
                    <i class="bx bx-x"></i>
                </button>
            </div>
            
            <div class="modal-body p-0">
                <div class="row g-0">
                    <!-- Left Section: Details (The "Carded" Design) -->
                    <div class="col-md-5 p-4" style="background: #f8fafc; border-right: 1px solid #edf2f7;">
                        <div class="mb-4">
                            <label class="form-label-hitech">EMPLOYEE INFO</label>
                            <div class="card border-0 shadow-sm p-3 mt-2" style="border-radius: 12px; background: #fff;">
                                <div class="d-flex align-items-center gap-3">
                                    <div id="userAvatarContainer"></div>
                                    <div class="overflow-hidden">
                                        <h6 class="mb-0 fw-bold text-dark text-truncate" id="userNameLabel">...</h6>
                                        <small class="text-muted fw-semibold" id="userCode">...</small>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="mb-4">
                            <label class="form-label-hitech">LEAVE TYPE</label>
                            <div class="card border-0 shadow-sm p-3 mt-2 d-flex flex-row align-items-center gap-3" style="border-radius: 12px; background: #fff;">
                                <div class="icon-sq-teal">
                                    <i class="bx bx-purchase-tag"></i>
                                </div>
                                <span class="fw-bold text-dark" id="leaveType">...</span>
                            </div>
                        </div>

                        <div class="row g-3 mb-4">
                            <div class="col-6">
                                <label class="form-label-hitech">START DATE</label>
                                <div class="card border-0 shadow-sm p-3 mt-2 text-center fw-bold text-dark fs-6" style="border-radius: 12px; background: #fff;" id="fromDate">...</div>
                            </div>
                            <div class="col-6">
                                <label class="form-label-hitech">END DATE</label>
                                <div class="card border-0 shadow-sm p-3 mt-2 text-center fw-bold text-dark fs-6" style="border-radius: 12px; background: #fff;" id="toDate">...</div>
                            </div>
                        </div>

                        <div class="mb-2">
                            <label class="form-label-hitech">REASON FOR LEAVE</label>
                            <div class="card border-0 shadow-sm p-3 mt-2 text-dark" style="border-radius: 12px; background: #fff; min-height: 80px; font-size: 0.95rem; line-height: 1.5;" id="userNotes">...</div>
                        </div>
                        
                        <div id="documentHide" style="display: none;" class="mt-4">
                            <label class="form-label-hitech">ATTACHMENT</label>
                            <div class="card border-0 shadow-sm p-2 mt-2" style="border-radius: 12px; background: #fff;">
                                <a href="#" class="glightbox">
                                    <img id="document" src="" class="img-fluid rounded shadow-sm" style="max-height: 120px; width: 100%; object-fit: cover;">
                                </a>
                                <a id="pdfPreview" href="#" target="_blank" style="display: none; text-decoration: none; color: inherit;"></a>
                            </div>
                        </div>
                    </div>

                    <!-- Right Section: Administrative Action -->
                    <div class="col-md-7 p-4 bg-white d-flex flex-column">
                        <div class="d-flex justify-content-between align-items-center mb-4">
                            <label class="form-label-hitech m-0">ADMINISTRATIVE ACTION</label>
                            <div id="statusDiv"></div>
                        </div>

                        <div class="mb-1">
                             <label class="form-label-hitech mb-2">OFFICIAL REMARKS</label>
                        </div>

                        <form id="leaveRequestForm" action="{{ route('leaveRequests.actionAjax') }}" method="POST" style="display:none;" class="flex-grow-1 d-flex flex-column">
                            @csrf
                            <input type="hidden" name="id" id="id">
                            <input type="hidden" name="status" id="statusInput">
                            
                            <div class="mb-4 flex-grow-1">
                                <textarea class="form-control workspace-textarea-original" id="adminNotes" name="adminNotes" rows="10" placeholder="Document the reason for this decision here..."></textarea>
                            </div>

                            <div class="d-flex align-items-center justify-content-between mb-4">
                                <div class="d-flex align-items-center gap-2 text-muted">
                                    <i class="bx bx-time-five fs-5"></i>
                                    <span class="small fw-semibold">Requested: <span id="createdAt">...</span></span>
                                </div>
                                <span class="badge bg-label-info px-4 py-2 rounded-pill fw-bold" style="font-size: 0.85rem;"><span id="totalDays">0</span> <span id="dayLabel">Days</span></span>
                            </div>

                            <div class="d-flex gap-3 mt-auto">
                                <button type="button" class="btn btn-hitech-cancel flex-grow-1 fw-bold" data-bs-dismiss="modal">CLOSE</button>
                                <button type="button" onclick="submitDecision('rejected')" class="btn btn-alert flex-grow-1" id="btnReject">
                                    <i class="bx bx-x me-1 fs-5"></i>REJECT
                                </button>
                                <button type="button" onclick="submitDecision('approved')" class="btn btn-hitech flex-grow-1" id="btnApprove">
                                    <i class="bx bx-check me-1 fs-5"></i>APPROVE
                                </button>
                            </div>
                            <span id="remarksRequired" class="text-danger text-center mt-2 small fw-bold" style="display:none;">* REASON IS REQUIRED FOR REJECTION</span>
                        </form>

                        <div id="alreadyRespondedNotice" class="text-center my-auto py-5" style="display: none">
                            <div class="mb-3">
                                <i class="bx bx-lock-alt text-success" style="font-size: 4rem;"></i>
                            </div>
                            <h4 class="fw-bold text-dark">Decision Finalized</h4>
                            <p class="text-muted small px-5">This request has reached a final state and is now locked for auditing purposes.</p>
                            <button type="button" class="btn btn-secondary px-5 py-2 mt-3" data-bs-dismiss="modal" style="border-radius: 10px;">Return</button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
/* Modal Specific overrides if any */
</style>
