<div class="offcanvas offcanvas-end" tabindex="-1" id="offcanvasAddOrUpdatePlan" aria-labelledby="offcanvasPlanLabel">
  <div class="offcanvas-header" style="background: linear-gradient(135deg, #004d4d 0%, #006666 100%); border-bottom: none;">
    <div class="d-flex align-items-center gap-3">
      <div class="modal-icon-header"><i class="bx bx-package fs-3"></i></div>
      <h5 id="offcanvasPlanLabel" class="offcanvas-title text-white fw-bold mb-0">@lang('Create Plan')</h5>
    </div>
    <button type="button" class="btn-close-hitech" data-bs-dismiss="offcanvas" aria-label="Close"><i class="bx bx-x"></i></button>
  </div>
  <div class="offcanvas-body p-4">
    <form class="pt-0" id="planForm">
      <input type="hidden" name="id" id="id">
      <input type="hidden" name="status" id="status">
      <div class="mb-4">
        <label class="form-label-hitech" for="name">@lang('Name') <span class="text-danger">*</span></label>
        <input type="text" class="form-control form-control-hitech" id="name" placeholder="@lang('Enter name')" name="name" />
      </div>
      <div class="mb-4">
        <label class="form-label-hitech" for="duration">@lang('Duration') <span class="text-danger">*</span></label>
        <input type="number" class="form-control form-control-hitech" id="duration" placeholder="@lang('Enter duration')" name="duration" />
      </div>
      <div class="mb-4">
        <label class="form-label-hitech" for="includedUsers">@lang('Included Users')</label>
        <input type="number" class="form-control form-control-hitech" id="includedUsers" placeholder="@lang('Enter included users')" name="includedUsers" />
      </div>
      <div class="mb-4">
        <label class="form-label-hitech" for="basePrice">@lang('Base Price')</label>
        <input type="number" class="form-control form-control-hitech" id="basePrice" placeholder="@lang('Enter base price')" name="basePrice" />
      </div>
      <div class="mb-4">
        <label class="form-label-hitech" for="perUserPrice">@lang('Per User Price')</label>
        <input type="number" class="form-control form-control-hitech" id="perUserPrice" placeholder="@lang('Enter per user price')" name="perUserPrice" />
      </div>
      <div class="mb-4">
        <label class="form-label-hitech" for="durationType">@lang('Duration Type') <span class="text-danger">*</span></label>
        <select class="form-select form-control-hitech" id="durationType" name="durationType">
          <option value="">Select Duration Type</option>
          <option value="days">Days</option>
          <option value="months">Months</option>
          <option value="years">Years</option>
        </select>
      </div>
      <div class="mb-5">
        <label class="form-label-hitech" for="description">@lang('Description')</label>
        <textarea class="form-control form-control-hitech" id="description" placeholder="@lang('Enter description')" name="description" rows="3"></textarea>
      </div>
      <div class="d-flex gap-3">
        <button type="submit" class="btn btn-primary flex-grow-1 data-submit">@lang('Create')</button>
        <button type="reset" class="btn btn-secondary px-4" data-bs-dismiss="offcanvas">@lang('Cancel')</button>
      </div>
    </form>
  </div>
</div>
