<div class="modal fade" id="modalAddOrUpdateLeaveType" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered" role="document">
        <div class="modal-content modal-content-hitech">
            <div class="modal-header modal-header-hitech">
                <div class="d-flex align-items-center">
                    <div class="modal-icon-header me-3">
                        <i class="bx bx-calendar-event fs-3"></i>
                    </div>
                    <h5 class="modal-title modal-title-hitech" id="modalLeaveTypeLabel">@lang('Create Leave Type')</h5>
                </div>
                <button type="button" class="btn-close-hitech" data-bs-dismiss="modal" aria-label="Close">
                    <i class="bx bx-x"></i>
                </button>
            </div>
            <form id="leaveTypeForm" method="POST">
                <div class="modal-body modal-body-hitech">
                    <input type="hidden" name="id" id="id">
                    <input type="hidden" name="status" id="status">
                    
                    <div class="row g-4">
                        <div class="col-md-6">
                            <label class="form-label-hitech" for="name">@lang('Name')<span class="text-danger">*</span></label>
                            <input type="text" class="form-control form-control-hitech" id="name" placeholder="@lang('e.g. Sick Leave')" name="name" required />
                        </div>
                        <div class="col-md-6">
                            <label class="form-label-hitech" for="code">@lang('Code')<span class="text-danger">*</span></label>
                            <input type="text" class="form-control form-control-hitech" id="code" placeholder="@lang('e.g. SL')" name="code" required />
                        </div>
                        <div class="col-md-12">
                            <label class="form-label-hitech" for="site_id">@lang('Unit')</label>
                            <select id="site_id" name="site_id" class="select2 form-select form-select-hitech" data-allow-clear="true">
                                <option value="">@lang('All Units (Global)')</option>
                                @foreach($sites as $site)
                                    <option value="{{ $site->id }}">{{ $site->name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-12 mt-1">
                            <label class="form-label-hitech" for="notes">@lang('Description')</label>
                            <textarea class="form-control form-control-hitech" id="notes" placeholder="@lang('Enter description...')" name="notes" rows="2"></textarea>
                        </div>



                        <!-- Advanced Configuration Section (3x2 Grid) -->
                        <div class="col-12 mt-4">
                            <div class="divider text-start mb-4">
                                <div class="divider-text text-dark fw-bold small text-uppercase">
                                    <i class="bx bx-cog me-1 text-primary"></i> @lang('Advanced Configuration')
                                </div>
                            </div>
                            
                            <div class="row g-3 row-cols-1 row-cols-md-3">
                                <!-- Paid Leave -->
                                <div class="col">
                                    <div class="hitech-config-card-light h-100 p-3">
                                        <div class="d-flex align-items-center justify-content-between">
                                            <div class="d-flex align-items-center">
                                                <div class="stat-icon-xs bg-label-purple me-2">
                                                    <i class="bx bx-dollar-circle"></i>
                                                </div>
                                                <span class="text-dark fw-bold small text-uppercase letter-spacing-05">@lang('Paid')</span>
                                            </div>
                                            <div class="hitech-toggle-wrapper">
                                                <input type="checkbox" id="isPaidToggle" class="hitech-switch-input" checked>
                                                <label for="isPaidToggle" class="hitech-switch-label"></label>
                                                <input type="hidden" name="isPaid" id="isPaid" value="1">
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <!-- Short Leave -->
                                <div class="col">
                                    <div class="hitech-config-card-light h-100 p-3">
                                        <div class="d-flex align-items-center justify-content-between">
                                            <div class="d-flex align-items-center">
                                                <div class="stat-icon-xs bg-label-primary me-2">
                                                    <i class="bx bx-time-five"></i>
                                                </div>
                                                <span class="text-dark fw-bold small text-uppercase letter-spacing-05">@lang('Short')</span>
                                            </div>
                                            <div class="hitech-toggle-wrapper">
                                                <input type="checkbox" id="isShortLeaveToggle" class="hitech-switch-input">
                                                <label for="isShortLeaveToggle" class="hitech-switch-label"></label>
                                                <input type="hidden" name="isShortLeave" id="isShortLeave" value="0">
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <!-- Proof Required -->
                                <div class="col">
                                    <div class="hitech-config-card-light h-100 p-3">
                                        <div class="d-flex align-items-center justify-content-between">
                                            <div class="d-flex align-items-center">
                                                <div class="stat-icon-xs bg-label-info me-2">
                                                    <i class="bx bx-check-shield"></i>
                                                </div>
                                                <span class="text-dark fw-bold small text-uppercase letter-spacing-05">@lang('Proof')</span>
                                            </div>
                                            <div class="hitech-toggle-wrapper">
                                                <input type="checkbox" id="isProofRequiredToggle" class="hitech-switch-input">
                                                <label for="isProofRequiredToggle" class="hitech-switch-label"></label>
                                                <input type="hidden" name="isProofRequired" id="isProofRequired" value="0">
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <!-- Carry Forward -->
                                <div class="col">
                                    <div class="hitech-config-card-light h-100 p-3">
                                        <div class="d-flex align-items-center justify-content-between">
                                            <div class="d-flex align-items-center">
                                                <div class="stat-icon-xs bg-label-success me-2">
                                                    <i class="bx bx-redo"></i>
                                                </div>
                                                <span class="text-dark fw-bold small text-uppercase letter-spacing-05">@lang('Carry Fwd')</span>
                                            </div>
                                            <div class="hitech-toggle-wrapper">
                                                <input type="checkbox" id="isCarryForwardToggle" class="hitech-switch-input" checked>
                                                <label for="isCarryForwardToggle" class="hitech-switch-label"></label>
                                                <input type="hidden" name="isCarryForward" id="isCarryForward" value="1">
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <!-- WFH/OFF Split -->
                                <div class="col">
                                    <div class="hitech-config-card-light h-100 p-3">
                                        <div class="d-flex align-items-center justify-content-between">
                                            <div class="d-flex align-items-center">
                                                <div class="stat-icon-xs bg-label-warning me-2">
                                                    <i class="bx bx-git-repo-forked"></i>
                                                </div>
                                                <span class="text-dark fw-bold small text-uppercase letter-spacing-05">@lang('WFH Split')</span>
                                            </div>
                                            <div class="hitech-toggle-wrapper">
                                                <input type="checkbox" id="isSplitEntitlementToggle" class="hitech-switch-input">
                                                <label for="isSplitEntitlementToggle" class="hitech-switch-label"></label>
                                                <input type="hidden" name="isSplitEntitlement" id="isSplitEntitlement" value="0">
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <!-- Strict Rules -->
                                <div class="col">
                                    <div class="hitech-config-card-light h-100 p-3">
                                        <div class="d-flex align-items-center justify-content-between">
                                            <div class="d-flex align-items-center">
                                                <div class="stat-icon-xs bg-label-secondary me-2">
                                                    <i class="bx bx-shield-quarter"></i>
                                                </div>
                                                <span class="text-dark fw-bold small text-uppercase letter-spacing-05">@lang('Strict Rules')</span>
                                            </div>
                                            <div class="hitech-toggle-wrapper">
                                                <input type="checkbox" id="isStrictRulesToggle" class="hitech-switch-input">
                                                <label for="isStrictRulesToggle" class="hitech-switch-label"></label>
                                                <input type="hidden" name="isStrictRules" id="isStrictRules" value="0">
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <!-- Consecutive Allowed -->
                                <div class="col">
                                    <div class="hitech-config-card-light h-100 p-3">
                                        <div class="d-flex align-items-center justify-content-between">
                                            <div class="d-flex align-items-center">
                                                <div class="stat-icon-xs bg-label-info me-2">
                                                    <i class="bx bx-list-ol"></i>
                                                </div>
                                                <span class="text-dark fw-bold small text-uppercase letter-spacing-05">@lang('Consecutive')</span>
                                            </div>
                                            <div class="hitech-toggle-wrapper">
                                                <input type="checkbox" id="isConsecutiveAllowedToggle" class="hitech-switch-input">
                                                <label for="isConsecutiveAllowedToggle" class="hitech-switch-label"></label>
                                                <input type="hidden" name="isConsecutiveAllowed" id="isConsecutiveAllowed" value="0">
                                            </div>
                                        </div>
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
