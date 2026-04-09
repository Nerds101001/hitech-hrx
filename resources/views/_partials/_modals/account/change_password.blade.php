<div class="modal fade" id="changePasswordModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content modal-content-hitech">
      <div class="modal-header modal-header-hitech">
        <div class="d-flex align-items-center">
          <div class="modal-icon-header me-3"><i class="bx bx-lock-alt fs-3"></i></div>
          <h5 class="modal-title modal-title-hitech">@lang('Change Password')</h5>
        </div>
        <button type="button" class="btn-close-hitech" data-bs-dismiss="modal" aria-label="Close"><i class="bx bx-x"></i></button>
      </div>
      <div class="modal-body modal-body-hitech">
        <form id="changePasswordForm" action="{{ route('account.changePassword') }}" method="POST">
          @csrf
          @method('POST')
          <div class="mb-4">
            <label class="form-label-hitech" for="oldPassword">@lang('Old Password')</label>
            <input type="password" class="form-control form-control-hitech" id="oldPassword" name="oldPassword" placeholder="@lang('Enter old password')" />
          </div>
          <div class="mb-4">
            <label class="form-label-hitech" for="newPassword">@lang('New Password')</label>
            <input type="password" class="form-control form-control-hitech" id="newPassword" name="newPassword" placeholder="@lang('Enter new password')" />
          </div>
          <div class="modal-footer border-0 px-0 pb-0">
            <button type="reset" class="btn btn-label-secondary px-4" data-bs-dismiss="modal">@lang('Cancel')</button>
            <button type="submit" class="btn btn-hitech px-5 d-flex align-items-center">
              <span>@lang('Update Password')</span>
              <i class="bx bx-check-circle ms-2 fs-5"></i>
            </button>
          </div>
        </form>
      </div>
    </div>
  </div>
</div>
