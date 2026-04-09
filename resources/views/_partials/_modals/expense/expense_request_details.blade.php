<div class="modal fade" id="modalExpenseRequestDetails" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered" role="document">
        <div class="modal-content modal-content-hitech">
            <!-- Premium HITECH Header -->
            <div class="modal-header modal-header-hitech">
                <div class="d-flex align-items-center">
                    <div class="modal-icon-header me-3">
                        <i class="bx bx-receipt fs-3"></i>
                    </div>
                    <h5 class="modal-title modal-title-hitech">Review Expense: <span id="userName"></span></h5>
                </div>
                <button type="button" class="btn-close-hitech" data-bs-dismiss="modal" aria-label="Close">
                    <i class="bx bx-x"></i>
                </button>
            </div>
            
            <div class="modal-body p-0">
                <div class="row g-0">
                    <!-- Left: Details -->
                    <div class="col-md-5 p-4" style="background: #fdfdfd; border-right: 1px solid #eee;">
                        <div class="mb-4">
                            <label class="hitech-label-caps">Employee Information</label>
                            <div class="d-flex align-items-center p-3 border rounded-3 bg-white">
                                <div id="userAvatarContainer" class="me-3">
                                    <div class="avatar avatar-md">
                                        <span class="avatar-initial rounded-circle bg-primary shadow-sm" id="userInitials"></span>
                                    </div>
                                </div>
                                <div class="overflow-hidden">
                                    <h6 class="mb-0 fw-bold text-dark" id="userNameLabel"></h6>
                                    <small class="text-secondary fw-bold" style="font-size: 0.75rem;" id="userCode"></small>
                                </div>
                            </div>
                        </div>

                        <div class="mb-4">
                            <label class="hitech-label-caps">Expense Category</label>
                            <div class="p-3 border rounded-3 bg-white fw-bold text-dark">
                                <i class="bx bx-category me-2 text-primary"></i>
                                <span id="expenseType"></span>
                            </div>
                        </div>

                        <div class="row g-3">
                            <div class="col-6">
                                <label class="hitech-label-caps">Expense Date</label>
                                <div class="p-3 border rounded-3 bg-white text-center fw-bold text-dark" id="forDate"></div>
                            </div>
                            <div class="col-6">
                                <label class="hitech-label-caps">Claimed Amount</label>
                                <div class="p-3 border rounded-3 bg-white text-center fw-bold text-success" style="font-size: 1.1rem;">
                                    {{ $settings->currency_symbol }}<span id="amountDisplay"></span>
                                </div>
                            </div>
                        </div>

                        <div class="mt-4">
                            <label class="hitech-label-caps">User Remarks/Description</label>
                            <div class="p-3 border rounded-3 bg-white text-dark shadow-none" style="min-height: 80px; font-size: 0.9rem;" id="userNotes"></div>
                        </div>

                        <div class="mt-4" id="documentHide" style="display: none">
                            <label class="hitech-label-caps">Tax Invoice / Receipt</label>
                            <a href="#" class="glightbox d-block p-1 border rounded-3 bg-white">
                                <img id="document" class="img-fluid rounded" src="" style="width: 100%; max-height: 120px; object-fit: cover;">
                            </a>
                        </div>
                    </div>

                    <!-- Right: Action -->
                    <div class="col-md-7 p-4 bg-white d-flex flex-column">
                        <div class="d-flex justify-content-between align-items-center mb-4 border-bottom pb-3">
                            <h6 class="mb-0 fw-bold text-dark text-uppercase tracking-wider">Administrative Action</h6>
                            <div id="statusDiv"></div>
                        </div>

                        <form id="expenseRequestForm" action="{{ route('expenseRequests.actionAjax') }}" method="POST" style="display:none;" class="flex-grow-1 d-flex flex-column">
                            @csrf
                            <input type="hidden" name="id" id="id">
                            <input type="hidden" name="status" id="statusInput">
                            
                            <div class="mb-4" id="approvedAmountDiv">
                                <label class="hitech-label-caps">Approved Amount ({{ $settings->currency_symbol }})</label>
                                <div class="input-group input-group-lg">
                                    <span class="input-group-text bg-white border-2 border-end-0" style="border-radius: 10px 0 0 10px;"><i class="bx bx-check-double text-success"></i></span>
                                    <input type="number" step="0.01" class="form-control border-2 border-start-0" id="approvedAmount" name="approvedAmount" style="border-radius: 0 10px 10px 0; font-weight: 800; color: #000;">
                                </div>
                            </div>

                            <div class="flex-grow-1">
                                <label class="hitech-label-caps">Official Audit Remarks <span id="remarksRequired" class="text-danger" style="display:none">*</span></label>
                                <textarea class="form-control border-2" id="adminRemarks" name="adminRemarks" rows="5" placeholder="Document the reason for this decision here..." style="border-radius: 10px; font-size: 0.95rem; color: #333;"></textarea>
                            </div>
                            
                            <div class="mt-4 pt-4 border-top">
                                <div class="d-flex justify-content-between align-items-center mb-3">
                                    <small class="text-secondary fw-bold" id="createdAtDiv">
                                        <i class="bx bx-time-five"></i> Filed: <span id="createdAt"></span>
                                    </small>
                                    <span class="badge px-3 py-2" style="background: rgba(0, 90, 90, 0.08); color: #004a4a; font-size: 0.8rem; border-radius: 50px; font-weight: 700; border: 1px solid rgba(0, 90, 90, 0.1);">
                                        Claim: {{ $settings->currency_symbol }}<span id="amountInBadge"></span>
                                    </span>
                                </div>
                                
                                <div class="row g-2">
                                    <div class="col-4">
                                        <button type="button" class="btn btn-secondary w-100 py-2 fw-bold" data-bs-dismiss="modal" style="border-radius: 10px;">CLOSE</button>
                                    </div>
                                    <div class="col-4">
                                        <button type="button" onclick="submitExpenseDecision('rejected')" class="btn btn-danger w-100 py-2 fw-bold" id="btnReject" style="border-radius: 10px; background: #ef4444 !important; border: 0;">REJECT</button>
                                    </div>
                                    <div class="col-4">
                                        <button type="button" onclick="submitExpenseDecision('approved')" class="btn btn-teal w-100 py-2 fw-bold" id="btnApprove" style="border-radius: 10px; background: #007a7a !important; color: white !important; border: 0;">APPROVE</button>
                                    </div>
                                </div>
                            </div>
                        </form>

                        <div id="alreadyRespondedNotice" class="text-center my-auto p-5" style="display: none">
                            <i class="bx bx-check-shield text-success mb-4" style="font-size: 4rem;"></i>
                            <h4 class="fw-bold text-dark">Action Completed</h4>
                            <p class="text-secondary px-3" id="finalStatusMsg"></p>
                            <button type="button" class="btn btn-secondary px-5 py-2 mt-3" data-bs-dismiss="modal" style="border-radius: 10px;">Return</button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
.hitech-label-caps {
    display: block;
    font-size: 0.7rem;
    font-weight: 800;
    text-transform: uppercase;
    color: #64748b;
    margin-bottom: 8px;
    letter-spacing: 0.05em;
}
.text-teal { color: #007a7a !important; }
.btn-teal:hover { opacity: 0.9; transform: translateY(-1px); box-shadow: 0 4px 8px rgba(0, 77, 77, 0.2); }
.btn-danger:hover { opacity: 0.9; transform: translateY(-1px); box-shadow: 0 4px 8px rgba(239, 68, 68, 0.2); }
.form-control:focus { border-color: #007a7a !important; box-shadow: none !important; }
.tracking-wider { letter-spacing: 0.1em; }
</style>
