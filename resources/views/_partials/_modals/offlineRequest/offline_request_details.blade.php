<div class="offcanvas offcanvas-end" tabindex="-1" id="offcanvasOfflineRequestDetails" aria-labelledby="offcanvasOfflineRequestLabel">
  <div class="offcanvas-header" style="background: linear-gradient(135deg, #004d4d 0%, #006666 100%); border-bottom: none;">
    <div class="d-flex align-items-center gap-3">
      <div class="modal-icon-header"><i class="bx bx-receipt fs-3"></i></div>
      <h5 id="offcanvasOfflineRequestLabel" class="offcanvas-title text-white fw-bold mb-0">@lang('Offline Request Details')</h5>
    </div>
    <button type="button" class="btn-close-hitech" data-bs-dismiss="offcanvas" aria-label="Close"><i class="bx bx-x"></i></button>
  </div>
  <div class="offcanvas-body p-4">
    <div class="row mb-4">
      <div class="col-4 form-label-hitech">User:</div>
      <div class="col-8">
        <div class="fw-bold text-dark" id="userName"></div>
        <div class="text-muted small" id="userEmail"></div>
      </div>
    </div>
    <div class="row mb-4">
      <div class="col-4 form-label-hitech">Type:</div>
      <div class="col-8 fw-semibold text-dark" id="type"></div>
    </div>
    <div class="row mb-4">
      <div class="col-4 form-label-hitech">Plan Type:</div>
      <div class="col-8 fw-semibold text-dark" id="planName"></div>
    </div>
    <div class="row mb-4">
      <div class="col-4 form-label-hitech">Additional Users:</div>
      <div class="col-8 fw-semibold text-dark" id="additionalUsers"></div>
    </div>
    <div class="row mb-4">
      <div class="col-4 form-label-hitech">Total Amount:</div>
      <div class="col-8 fw-semibold text-dark" id="totalAmount"></div>
    </div>
    <div class="row mb-4">
      <div class="col-4 form-label-hitech">Created At:</div>
      <div class="col-8 text-muted" id="createdAt"></div>
    </div>
    <div class="row mb-5">
      <div class="col-4 form-label-hitech">Status:</div>
      <div class="col-8" id="statusDiv"></div>
    </div>

    <form action="{{ route('offlineRequests.actionAjax') }}" method="POST" id="offlineRequestForm" style="display: none;">
      @csrf
      <input type="hidden" name="id" id="id">
      <div class="mb-4" id="statusDDDiv">
        <label class="form-label-hitech">Action:</label>
        <select class="form-select form-control-hitech" id="status" name="status"></select>
      </div>
      <div class="mb-5">
        <label class="form-label-hitech">Admin Notes:</label>
        <textarea class="form-control form-control-hitech" id="adminNotes" name="adminNotes" rows="3"></textarea>
      </div>
      <div class="d-flex gap-3">
        <button type="submit" class="btn btn-primary flex-grow-1 data-submit" id="actionButton">@lang('Submit')</button>
        <button type="reset" class="btn btn-secondary px-4" data-bs-dismiss="offcanvas">@lang('Cancel')</button>
      </div>
    </form>
  </div>
</div>
