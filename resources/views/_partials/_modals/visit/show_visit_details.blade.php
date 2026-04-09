<div class="offcanvas offcanvas-end" tabindex="-1" id="offcanvasShowVisitDetails" aria-labelledby="offcanvasVisitLabel">
  <div class="offcanvas-header" style="background: linear-gradient(135deg, #004d4d 0%, #006666 100%); border-bottom: none;">
    <div class="d-flex align-items-center gap-3">
      <div class="modal-icon-header"><i class="bx bx-map-pin fs-3"></i></div>
      <h5 id="offcanvasVisitLabel" class="offcanvas-title text-white fw-bold mb-0">@lang('Visit Details')</h5>
    </div>
    <button type="button" class="btn-close-hitech" data-bs-dismiss="offcanvas" aria-label="Close"><i class="bx bx-x"></i></button>
  </div>
  <div class="offcanvas-body p-4">
    <div class="row mb-4">
      <div class="col-4 form-label-hitech">User:</div>
      <div class="col-8">
        <span id="userName" class="fw-bold text-dark d-block"></span>
        <small id="userCode" class="text-muted"></small>
      </div>
    </div>
    <hr class="my-3 opacity-10">
    <div class="row mb-4">
      <div class="col-4 form-label-hitech">Client:</div>
      <div class="col-8 fw-semibold text-dark" id="client"></div>
    </div>
    <hr class="my-3 opacity-10">
    <div class="row mb-4">
      <div class="col-4 form-label-hitech">Created At:</div>
      <div class="col-8 text-muted" id="createdAt"></div>
    </div>
    <hr class="my-3 opacity-10">
    <div class="row mb-4">
      <div class="col-4 form-label-hitech">Address:</div>
      <div class="col-8"><p id="address" class="text-wrap mb-0 text-dark"></p></div>
    </div>
    <hr class="my-3 opacity-10">
    <div class="row mb-4">
      <div class="col-4 form-label-hitech">Notes:</div>
      <div class="col-8"><p id="remarks" class="text-wrap mb-0 text-dark"></p></div>
    </div>
    <hr class="my-3 opacity-10">
    <div class="row">
      <div class="col-4 form-label-hitech">Image:</div>
      <div class="col-8">
        <img id="imageUrl" class="img-fluid rounded-3 border shadow-sm" src="https://placehold.co/100x100" alt="Visit" width="100" height="100">
      </div>
    </div>
  </div>
</div>
