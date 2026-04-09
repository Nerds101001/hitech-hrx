<div class="offcanvas offcanvas-end" tabindex="-1" id="offcanvasAddUser" aria-labelledby="offcanvasAddUserLabel">
  <div class="offcanvas-header" style="background: linear-gradient(135deg, #004d4d 0%, #006666 100%); border-bottom: none;">
    <div class="d-flex align-items-center gap-3">
      <div class="modal-icon-header"><i class="bx bx-user-plus fs-3"></i></div>
      <h5 id="offcanvasAddUserLabel" class="offcanvas-title text-white fw-bold mb-0">@lang('Add User')</h5>
    </div>
    <button type="button" class="btn-close-hitech" data-bs-dismiss="offcanvas" aria-label="Close"><i class="bx bx-x"></i></button>
  </div>
  <div class="offcanvas-body p-4">
    <form class="add-new-user pt-0" id="addNewUserForm">
      <input type="hidden" name="userId" id="userId">
      <div class="mb-4">
        <label class="form-label-hitech" for="firstName">@lang('First Name')</label>
        <input type="text" class="form-control form-control-hitech" id="firstName" placeholder="@lang('Enter first name')" name="firstName" autofocus />
      </div>
      <div class="mb-4">
        <label class="form-label-hitech" for="lastName">@lang('Last Name')</label>
        <input type="text" class="form-control form-control-hitech" id="lastName" placeholder="@lang('Enter last name')" name="lastName" />
      </div>
      <div class="mb-4">
        <label class="form-label-hitech" for="gender">@lang('Gender')</label>
        <select class="form-select form-control-hitech" id="gender" name="gender">
          <option value="male" {{ old('gender') == 'male' ? 'selected' : '' }}>Male</option>
          <option value="female" {{ old('gender') == 'female' ? 'selected' : '' }}>Female</option>
          <option value="other" {{ old('gender') == 'other' ? 'selected' : '' }}>Other</option>
        </select>
      </div>
      <div class="mb-4">
        <label class="form-label-hitech" for="email">@lang('Email')</label>
        <input type="text" id="email" class="form-control form-control-hitech" placeholder="@lang('Enter email')" name="email" />
      </div>
      <div class="mb-5">
        <label class="form-label-hitech" for="phone">@lang('Phone Number')</label>
        <input type="number" class="form-control form-control-hitech" id="phone" name="phone" placeholder="@lang('Enter phone number')" value="{{ old('phone') }}">
      </div>
      <div class="d-flex gap-3">
        <button type="submit" class="btn btn-hitech flex-grow-1 data-submit">@lang('Submit')</button>
        <button type="reset" class="btn btn-label-secondary px-4" data-bs-dismiss="offcanvas">@lang('Cancel')</button>
      </div>
    </form>
  </div>
</div>
