<div class="modal fade" id="modalAddOrUpdateExpenseType" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered" role="document">
        <div class="modal-content modal-content-hitech">
            <div class="modal-header modal-header-hitech">
                <div class="d-flex align-items-center">
                    <div class="modal-icon-header me-3">
                        <i class="bx bx-receipt fs-3"></i>
                    </div>
                    <h5 class="modal-title modal-title-hitech" id="modalExpenseTypeLabel">@lang('Create Expense Type')</h5>
                </div>
                <button type="button" class="btn-close-hitech" data-bs-dismiss="modal" aria-label="Close">
                    <i class="bx bx-x"></i>
                </button>
            </div>
            <form id="expenseTypeForm">
                <div class="modal-body modal-body-hitech">
                    <input type="hidden" name="id" id="id">
                    <input type="hidden" name="status" id="status">
                    
                    <div class="row g-4">
                        <div class="col-md-6">
                            <label class="form-label-hitech" for="name">@lang('Name')<span class="text-danger">*</span></label>
                            <input type="text" class="form-control form-control-hitech" id="name" placeholder="@lang('e.g. Travel')" name="name" required />
                        </div>
                        <div class="col-md-6">
                            <label class="form-label-hitech" for="code">@lang('Code')<span class="text-danger">*</span></label>
                            <input type="text" class="form-control form-control-hitech" id="code" placeholder="@lang('e.g. TRV')" name="code" required />
                        </div>
                        <div class="col-12">
                            <label class="form-label-hitech" for="notes">@lang('Description')</label>
                            <textarea class="form-control form-control-hitech" id="notes" placeholder="@lang('Enter description...')" name="notes" rows="3"></textarea>
                        </div>
                        
                        <div class="col-12">
                            <div class="interactive-toggle-card">
                                <div class="d-flex align-items-center justify-content-between p-3 bg-glass-teal border-teal-subtle">
                                    <div class="d-flex align-items-center">
                                        <div class="icon-stat-teal me-3">
                                            <i class="bx bx-camera fs-4"></i>
                                        </div>
                                        <div>
                                            <label class="form-label-hitech mb-0 text-white" for="isProofRequiredToggle">@lang('Proof Required')</label>
                                            <div class="small text-white-50 mt-1">Employee must upload a receipt</div>
                                        </div>
                                    </div>
                                    <div class="hitech-toggle-wrapper">
                                        <input class="hitech-switch-input" type="checkbox" id="isProofRequiredToggle">
                                        <label class="hitech-switch-label" for="isProofRequiredToggle"></label>
                                        <input type="hidden" name="isProofRequired" id="isProofRequired" value="0">
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer border-0 px-4 pb-4">
                    <button type="reset" class="btn btn-label-secondary px-4 h-px-45 d-flex align-items-center" data-bs-dismiss="modal">@lang('Cancel')</button>
                    <button type="submit" class="btn btn-hitech px-5 h-px-45 d-flex align-items-center data-submit">
                        <span class="submit-text">@lang('Save Changes')</span>
                        <i class="bx bx-check-circle ms-2 fs-5"></i>
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
