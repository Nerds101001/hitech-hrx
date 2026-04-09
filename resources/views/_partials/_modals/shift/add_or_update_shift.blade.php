<div class="modal fade" id="modalAddOrUpdateShift" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered" role="document">
        <div class="modal-content modal-content-hitech">
            <div class="modal-header modal-header-hitech">
                <div class="d-flex align-items-center">
                    <div class="modal-icon-header me-3">
                        <i class="bx bx-time-five fs-3"></i>
                    </div>
                    <h5 class="modal-title modal-title-hitech" id="modalShiftLabel">Add Shift</h5>
                </div>
                <button type="button" class="btn-close-hitech" data-bs-dismiss="modal" aria-label="Close">
                    <i class="bx bx-x"></i>
                </button>
            </div>
            <form class="add-edit-shift-form" id="shiftForm" onsubmit="return false;">
                <div class="modal-body modal-body-hitech">
                    @csrf
                    <input type="hidden" name="_method" id="shiftMethod" value="POST">
                    <input type="hidden" name="id" id="shift_id" value="">

                    <div class="row g-4">
                        <div class="col-md-6">
                            <label class="form-label-hitech" for="shiftName">Shift Name <span class="text-danger">*</span></label>
                            <input type="text" class="form-control form-control-hitech" id="shiftName" placeholder="e.g., General Shift" name="name" required />
                            <div class="invalid-feedback"></div>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label-hitech" for="shiftCode">Shift Code <span class="text-danger">*</span></label>
                            <input type="text" class="form-control form-control-hitech" id="shiftCode" placeholder="e.g., GS01" name="code" required />
                            <div class="invalid-feedback"></div>
                        </div>

                        <div class="col-md-6">
                            <label for="startTime" class="form-label-hitech">Start Time <span class="text-danger">*</span></label>
                            <input type="text" class="form-control form-control-hitech flatpickr-input" placeholder="HH:MM" id="startTime" name="start_time" required readonly="readonly" />
                            <div class="invalid-feedback"></div>
                        </div>
                        <div class="col-md-6">
                            <label for="endTime" class="form-label-hitech">End Time <span class="text-danger">*</span></label>
                            <input type="text" class="form-control form-control-hitech flatpickr-input" placeholder="HH:MM" id="endTime" name="end_time" required readonly="readonly" />
                            <div class="invalid-feedback"></div>
                        </div>


                        <div class="col-12 mt-4">
                            <div class="interactive-toggle-card p-3 rounded-4 bg-glass-teal border-teal-subtle">
                                <div class="d-flex align-items-center justify-content-between">
                                    <div class="d-flex align-items-center">
                                        <div class="icon-stat-teal me-3 p-2 bg-white bg-opacity-10 rounded-3 text-white">
                                            <i class="bx bx-infinite fs-4"></i>
                                        </div>
                                        <div>
                                            <label class="form-label-hitech mb-0 text-white" for="isFlexible">Flexible Shift Profile</label>
                                            <div class="small text-white-50 mt-1">Ignore fixed timings, use punch-in window</div>
                                        </div>
                                    </div>
                                    <div class="hitech-toggle-wrapper">
                                        <input type="checkbox" class="hitech-switch-input" id="isFlexible" name="is_flexible" value="1">
                                        <label class="hitech-switch-label" for="isFlexible"></label>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div id="flexibleTimingSection" class="col-12 d-none">
                            <div class="row g-4 p-4 border rounded-4 border-dashed border-teal border-opacity-25 bg-teal bg-opacity-5">
                                <div class="col-md-4">
                                    <label class="form-label-hitech" for="flexStart">Window Start</label>
                                    <input type="text" class="form-control form-control-hitech flatpickr-input" placeholder="09:00" id="flexStart" name="flex_start_time" readonly="readonly" />
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label-hitech" for="flexEnd">Window End</label>
                                    <input type="text" class="form-control form-control-hitech flatpickr-input" placeholder="11:00" id="flexEnd" name="flex_end_time" readonly="readonly" />
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label-hitech" for="minHours">Min Hours</label>
                                    <input type="number" class="form-control form-control-hitech text-center" id="minHours" name="min_working_hours" value="8" step="0.5" />
                                </div>
                            </div>
                        </div>

                        <div class="col-12">
                            <label class="form-label-hitech" for="shiftNotes">Notes</label>
                            <textarea class="form-control form-control-hitech" id="shiftNotes" name="notes" rows="2" placeholder="Optional notes..."></textarea>
                            <div class="invalid-feedback"></div>
                        </div>

                        <div class="col-12">
                            <small class="text-danger general-error-message"></small>
                        </div>
                    </div>
                </div>
                <div class="modal-footer border-0 px-4 pb-4">
                    <button type="reset" class="btn btn-label-secondary px-4 h-px-45 d-flex align-items-center" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-hitech px-5 h-px-45 d-flex align-items-center data-submit" id="submitShiftBtn">
                        <span>Submit</span>
                        <i class="bx bx-check-circle ms-2 fs-5"></i>
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
