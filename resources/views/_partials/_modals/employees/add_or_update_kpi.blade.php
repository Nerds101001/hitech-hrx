<div class="modal fade" id="modalAddKpi" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content modal-content-hitech">
            <div class="modal-header modal-header-hitech">
                <div class="d-flex align-items-center">
                    <div class="modal-icon-header me-3">
                        <i class="bx bx-trending-up"></i>
                    </div>
                    <h5 class="modal-title modal-title-hitech mb-0">Define Performance KPI</h5>
                </div>
                <button type="button" class="btn-close-hitech" data-bs-dismiss="modal">
                    <i class="bx bx-x"></i>
                </button>
            </div>
            <form id="addKpiForm" action="{{ route('employees.addOrUpdateSalesTarget') }}" method="POST">
                @csrf
                <input type="hidden" name="userId" value="{{ $user->id }}">
                <input type="hidden" name="kpiId" id="modalKpiId" value="">
                <div class="modal-body modal-body-hitech">
                    <div class="row g-4">
                        <div class="col-md-6">
                            <label class="form-label-hitech">Evaluation Category <span class="text-danger">*</span></label>
                            <select name="kpi_type" id="modalKpiCategory" class="form-select form-select-hitech">
                                <option value="KPI">KPI (Key Performance Indicator)</option>
                                <option value="KRA">KRA (Key Result Area)</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label-hitech">MNC Grade System <span class="text-danger">*</span></label>
                            <select name="grade_system" id="modalKpiGrade" class="form-select form-select-hitech">
                                <option value="Standard">Standard Level</option>
                                <option value="Bronze Grade">Bronze Grade</option>
                                <option value="Silver Grade">Silver Grade</option>
                                <option value="Gold Grade">Gold Grade</option>
                                <option value="Platinum (MNC)">Platinum Grade (MNC)</option>
                                <option value="Diamond (MNC)">Diamond Grade (MNC)</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label-hitech">Target Metric Name <span class="text-danger">*</span></label>
                            <input type="text" name="metric_name" id="modalKpiMetric" class="form-control form-control-hitech" placeholder="e.g. Sales Volume, CSAT Score" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label-hitech">Evaluation Period <span class="text-danger">*</span></label>
                            <select name="target_type" id="modalKpiPeriod" class="form-select form-select-hitech">
                                <option value="monthly">Monthly</option>
                                <option value="quarterly">Quarterly</option>
                                <option value="half_yearly">Half Yearly</option>
                                <option value="yearly">Yearly</option>
                            </select>
                        </div>
                        <div class="col-12">
                            <label class="form-label-hitech">Strategic Description</label>
                            <textarea name="description" id="modalKpiDescription" class="form-control form-control-hitech" rows="2" placeholder="Define how this metric aligns with company goals..."></textarea>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label-hitech">Quantitative Goal <span class="text-danger">*</span></label>
                            <div class="input-group input-group-merge">
                                <span class="input-group-text bg-light border-0" id="currencyPrefix">{{ $settings->currency_symbol ?? '₹' }}</span>
                                <input type="number" step="0.01" name="target_amount" id="modalKpiGoal" class="form-control form-control-hitech" placeholder="0.00" required>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label-hitech">Measurement Type</label>
                            <select name="incentive_type" id="modalKpiType" class="form-select form-select-hitech">
                                <option value="percentage">Percentage (%)</option>
                                <option value="fixed">Fixed Amount</option>
                                <option value="points">Points / Index</option>
                                <option value="count">Numerical Count</option>
                            </select>
                        </div>
                    </div>
                </div>
                <div class="modal-footer border-0 px-4 pb-4 d-flex justify-content-end gap-3">
                    <button type="button" class="btn btn-hitech-modal-cancel" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-hitech-modal-submit">Deploy KPI <i class="bx bx-rocket ms-1"></i></button>
                </div>
            </form>
        </div>
    </div>
</div>
