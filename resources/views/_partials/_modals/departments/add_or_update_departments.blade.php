<div class="modal fade" id="modalAddOrUpdateDepartment" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content modal-content-hitech">
      <div class="modal-header modal-header-hitech">
        <div class="d-flex align-items-center">
            <div class="modal-icon-header me-3">
                <i class="bx bx-building fs-3"></i>
            </div>
            <h5 class="modal-title modal-title-hitech" id="modalDepartmentLabel">@lang('Create Department')</h5>
        </div>
        <button type="button" class="btn-close-hitech" data-bs-dismiss="modal" aria-label="Close">
            <i class="bx bx-x"></i>
        </button>
      </div>
      <div class="modal-body modal-body-hitech">
        <form id="addNewDepartmentForm" method="POST">
          <input type="hidden" name="departmentId" id="departmentId">
          
          <div class="row">
            <div class="col-md-6 mb-5">
              <label class="form-label form-label-hitech" for="name">@lang('Department Name')<span class="text-danger">*</span></label>
              <input type="text" class="form-control form-control-hitech" id="name" name="name" placeholder="@lang('e.g. Human Resources')" required />
            </div>
            <div class="col-md-6 mb-5">
              <label class="form-label form-label-hitech" for="code">@lang('Department Code')<span class="text-danger">*</span></label>
              <input type="text" class="form-control form-control-hitech" id="code" name="code" placeholder="@lang('e.g. HR')" required />
            </div>
          </div>

          <div class="mb-5">
            <label class="form-label form-label-hitech" for="parent_department">@lang('Parent Department')</label>
            <select class="form-select form-select-hitech select2" id="parent_department" name="parent_department">
              <option value="">@lang('Select parent department')</option>
            </select>
          </div>

          <div class="mb-5">
            <label class="form-label form-label-hitech" for="manager_ids">@lang('Assign Managers')</label>
            <select class="form-select select2-modal-department" id="manager_ids" name="manager_ids[]" multiple>
              @foreach($users as $user)
                <option value="{{ $user->id }}">{{ $user->first_name }} {{ $user->last_name }}</option>
              @endforeach
            </select>
          </div>

          <div class="mb-5">
            <label class="form-label form-label-hitech" for="notes">@lang('Description')</label>
            <textarea class="form-control form-control-hitech" id="notes" name="notes" rows="3" placeholder="@lang('Optional description...')"></textarea>
          </div>

          <div class="d-flex justify-content-end gap-3 mt-4">
            <button type="reset" class="btn btn-label-secondary px-4 h-px-45 d-flex align-items-center" data-bs-dismiss="modal">@lang('Cancel')</button>
            <button type="submit" class="btn btn-hitech px-5 h-px-45 d-flex align-items-center data-submit">
              <span class="submit-text">@lang('Save Department')</span>
              <i class="bx bx-check-circle ms-2 fs-5"></i>
            </button>
          </div>
        </form>
      </div>
    </div>
  </div>
</div>
