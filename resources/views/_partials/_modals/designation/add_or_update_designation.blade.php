<div class="modal fade" id="modalAddOrUpdateDesignation" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content modal-content-hitech">
      <div class="modal-header modal-header-hitech">
        <div class="d-flex align-items-center">
            <div class="modal-icon-header me-3">
                <i class="bx bx-briefcase fs-3"></i>
            </div>
            <h5 class="modal-title modal-title-hitech" id="modalDesignationLabel">@lang('Create Designation')</h5>
        </div>
        <button type="button" class="btn-close-hitech" data-bs-dismiss="modal" aria-label="Close">
            <i class="bx bx-x"></i>
        </button>
      </div>
      <div class="modal-body modal-body-hitech">
        <form id="designationForm" method="POST">
          <input type="hidden" name="id" id="id">
          <input type="hidden" name="status" id="status">
          
          <div class="row">
            <div class="col-md-6 mb-5">
              <label class="form-label form-label-hitech" for="name">@lang('Designation Name')<span class="text-danger">*</span></label>
              <input type="text" class="form-control form-control-hitech" id="name" name="name" placeholder="@lang('e.g. Senior Manager')" required />
            </div>
            <div class="col-md-6 mb-5">
              <label class="form-label form-label-hitech" for="code">@lang('Designation Code')<span class="text-danger">*</span></label>
              <input type="text" class="form-control form-control-hitech" id="code" name="code" placeholder="@lang('e.g. SM')" required />
            </div>
          </div>

          <div class="mb-5">
            <label class="form-label form-label-hitech" for="department_id">@lang('Department')</label>
            <select class="form-select form-select-hitech select2" id="department_id" name="department_id">
              <option value="">@lang('Select department')</option>
            </select>
          </div>

          <div class="mb-5">
            <label class="form-label form-label-hitech" for="notes">@lang('Description')</label>
            <textarea class="form-control form-control-hitech" id="notes" name="notes" rows="3" placeholder="@lang('Optional description...')"></textarea>
          </div>

          <div class="interactive-toggle-card mb-6">
            <div class="d-flex align-items-center justify-content-between p-3 rounded-4 bg-glass-teal border-teal-subtle">
              <div class="d-flex align-items-center">
                <div class="icon-stat-teal me-3 p-2 bg-white bg-opacity-10 rounded-3 text-white">
                  <i class="bx bx-shield-quarter fs-4"></i>
                </div>
                <div>
                  <label class="form-label form-label-hitech mb-0 text-white" for="is_approver">@lang('Approval Rights')</label>
                  <div class="small text-white-50 mt-1">Can approve requests/leaves</div>
                </div>
              </div>
              <div class="hitech-toggle-wrapper">
                <input class="hitech-switch-input" type="checkbox" id="is_approver" name="is_approver">
                <label class="hitech-switch-label" for="is_approver"></label>
              </div>
            </div>
          </div>

          <div class="d-flex justify-content-end gap-3 mt-4">
            <button type="reset" class="btn btn-label-secondary px-4 h-px-45 d-flex align-items-center" data-bs-dismiss="modal">@lang('Cancel')</button>
            <button type="submit" class="btn btn-hitech px-5 h-px-45 d-flex align-items-center data-submit">
              <span class="submit-text">@lang('Save Designation')</span>
              <i class="bx bx-check-circle ms-2 fs-5"></i>
            </button>
          </div>
        </form>
      </div>
    </div>
  </div>
</div>
