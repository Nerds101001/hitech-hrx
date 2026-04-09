<div class="offcanvas offcanvas-end" tabindex="-1" id="offcanvasCreateTenant" aria-labelledby="offcanvasCreateTenantLabel">
  <div class="offcanvas-header" style="background: linear-gradient(135deg, #004d4d 0%, #006666 100%); border-bottom: none;">
    <div class="d-flex align-items-center gap-3">
      <div class="modal-icon-header"><i class="bx bx-buildings fs-3"></i></div>
      <h5 id="offcanvasCreateTenantLabel" class="offcanvas-title text-white fw-bold mb-0">@lang('Create Tenant')</h5>
    </div>
    <button type="button" class="btn-close-hitech" data-bs-dismiss="offcanvas" aria-label="Close"><i class="bx bx-x"></i></button>
  </div>
  <div class="offcanvas-body p-4">
    <form class="pt-0" id="createTenantForm" action="{{ route('tenant.store') }}" method="POST">
      @csrf
      <div class="mb-4">
        <label class="form-label-hitech" for="name">@lang('Name') <span class="text-danger">*</span></label>
        <input type="text" class="form-control form-control-hitech" id="name" placeholder="@lang('Enter name')" name="name" />
      </div>
      <div class="mb-4">
        <label class="form-label-hitech" for="companyName">@lang('Company Name') <span class="text-danger">*</span></label>
        <input type="text" class="form-control form-control-hitech" id="companyName" placeholder="@lang('Enter company name')" name="companyName" />
      </div>
      <div class="mb-5">
        <label class="form-label-hitech" for="emailDomain">@lang('Email Domain') <span class="text-danger">*</span></label>
        <input type="text" class="form-control form-control-hitech" id="emailDomain" placeholder="@lang('Enter email domain')" name="emailDomain" />
      </div>
      <div class="d-flex gap-3">
        <button type="submit" class="btn btn-hitech flex-grow-1 data-submit">@lang('Create')</button>
        <button type="reset" class="btn btn-label-secondary px-4" data-bs-dismiss="offcanvas">@lang('Cancel')</button>
      </div>
    </form>
  </div>
</div>
