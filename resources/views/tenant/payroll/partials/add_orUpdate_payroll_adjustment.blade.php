<div class="modal fade" id="offcanvasPayrollAdjustment" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content modal-content-hitech">
            <div class="modal-header modal-header-hitech">
                <div class="d-flex align-items-center">
                    <div class="modal-icon-header me-3">
                        <i class="bx bx-plus-circle"></i>
                    </div>
                    <h5 class="modal-title modal-title-hitech mb-0">Manage Payroll Adjustment</h5>
                </div>
                <button type="button" class="btn-close-hitech" data-bs-dismiss="modal">
                    <i class="bx bx-x"></i>
                </button>
            </div>
            
            <form action="{{ route('employees.addOrUpdatePayrollAdjustment') }}" method="POST" id="payrollAdjustmentForm">
                @csrf
                <input type="hidden" name="id" id="adjustmentId">
                <input type="hidden" name="userId" value="{{ $user->id }}">

                <div class="modal-body modal-body-hitech">
                    <div class="row g-4">
                        <div class="col-12">
                            <label class="form-label-hitech" for="adjustmentName">Adjustment Name <span class="text-danger">*</span></label>
                            <input type="text" class="form-control form-control-hitech" id="adjustmentName" name="adjustmentName" placeholder="e.g. Performance Bonus" required />
                        </div>

                        <div class="col-md-6">
                            <label class="form-label-hitech" for="adjustmentCode">Adjustment Code <span class="text-danger">*</span></label>
                            <input type="text" class="form-control form-control-hitech" id="adjustmentCode" name="adjustmentCode" placeholder="e.g. BONUS_2024" required />
                        </div>

                        <div class="col-md-6">
                            <label class="form-label-hitech" for="adjustmentType">Adjustment Type <span class="text-danger">*</span></label>
                            <select id="adjustmentType" name="adjustmentType" class="form-select form-select-hitech" required>
                                <option value="benefit">Benefit (Addition)</option>
                                <option value="deduction">Deduction (Subtraction)</option>
                            </select>
                        </div>

                        <div class="col-md-6">
                            <label class="form-label-hitech" for="adjustmentCategory">Amount Type</label>
                            <select id="adjustmentCategory" class="form-select form-select-hitech">
                                <option value="fixed">Fixed Amount</option>
                                <option value="percentage">Percentage</option>
                            </select>
                        </div>

                        <div class="col-md-6" id="percentageDiv" style="display:none;">
                            <label class="form-label-hitech" for="adjustmentPercentage">Percentage (%)</label>
                            <div class="input-group input-group-hitech">
                                <input type="number" step="0.01" class="form-control" id="adjustmentPercentage" name="adjustmentPercentage" placeholder="0.00" />
                                <span class="input-group-text">%</span>
                            </div>
                        </div>

                        <div class="col-md-6" id="amountDiv">
                            <label class="form-label-hitech" for="adjustmentAmount">Amount (₹)</label>
                            <div class="input-group input-group-hitech">
                                <span class="input-group-text"><i class="bx bx-rupee"></i></span>
                                <input type="number" step="0.01" class="form-control" id="adjustmentAmount" name="adjustmentAmount" placeholder="0.00" />
                            </div>
                        </div>

                        <div class="col-12">
                            <label class="form-label-hitech" for="adjustmentNotes">Notes / Description</label>
                            <textarea class="form-control form-control-hitech" id="adjustmentNotes" name="adjustmentNotes" rows="3" placeholder="Additional details..."></textarea>
                        </div>
                    </div>
                </div>

                <div class="modal-footer border-0 px-4 pb-4 d-flex justify-content-end gap-3">
                    <button type="button" class="btn btn-hitech-modal-cancel" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-hitech-modal-submit" id="adjustmentSubmitBtn">Save Adjustment</button>
                </div>
            </form>
        </div>
    </div>
</div>
