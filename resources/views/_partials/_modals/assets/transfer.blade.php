<!-- Transfer Asset Modal -->
<div class="modal fade" id="modalTransferAsset" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content modal-content-hitech">
      <div class="modal-header modal-header-hitech">
        <div class="d-flex align-items-center">
          <div class="modal-icon-header me-3">
            <i class="bx bx-transfer-alt fs-3"></i>
          </div>
          <h5 class="modal-title modal-title-hitech">Transfer Asset</h5>
        </div>
        <button type="button" class="btn-close-hitech" data-bs-dismiss="modal" aria-label="Close">
          <i class="bx bx-x"></i>
        </button>
      </div>
      <div class="modal-body modal-body-hitech">
        <input type="hidden" id="transfer_asset_id">
        <p class="mb-4">Currently with <strong id="transfer_current_user">-</strong>. Choose who it should move to.</p>
        <div class="mb-3">
          <label class="form-label form-label-hitech">Transfer To <span class="text-danger">*</span></label>
          <select id="transfer_user_id" class="form-select form-select-hitech select2-modal" required>
            <option value="">Select User</option>
            @if(isset($users))
              @foreach($users as $user)
              <option value="{{ $user->id }}">{{ $user->name_with_department }}</option>
              @endforeach
            @endif
          </select>
        </div>
        <div class="mb-2">
          <label class="form-label form-label-hitech">Notes <span class="text-muted small">(optional)</span></label>
          <textarea id="transfer_notes" class="form-control form-control-hitech" rows="2" placeholder="Reason for transfer..."></textarea>
        </div>
      </div>
      <div class="modal-footer border-0 px-4 pb-4">
        <button type="button" class="btn btn-label-secondary" data-bs-dismiss="modal">Cancel</button>
        <button type="button" class="btn btn-hitech px-4" id="confirmTransferBtn">Transfer</button>
      </div>
    </div>
  </div>
</div>
