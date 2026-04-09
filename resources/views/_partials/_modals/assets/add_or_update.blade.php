<!-- Add/Update Asset Modal -->
<div class="modal fade" id="modalAddOrUpdateAsset" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-lg modal-dialog-centered">
    <div class="modal-content modal-content-hitech">
      <div class="modal-header modal-header-hitech">
        <div class="d-flex align-items-center">
          <div class="modal-icon-header me-3">
            <i class="bx bx-package fs-3"></i>
          </div>
          <h5 class="modal-title modal-title-hitech" id="modalAssetLabel">Add Asset</h5>
        </div>
        <button type="button" class="btn-close-hitech" data-bs-dismiss="modal" aria-label="Close">
          <i class="bx bx-x"></i>
        </button>
      </div>
      <form id="assetForm" onsubmit="return false">
        <div class="modal-body modal-body-hitech">
          <input type="hidden" name="id" id="asset_id">
          <div class="row g-4">
            <div class="col-md-6 mb-2">
              <label class="form-label form-label-hitech">Asset Name <span class="text-muted small">(optional)</span></label>
              <div class="input-group input-group-merge shadow-none">
                <span class="input-group-text bg-transparent border-end-0"><i class="bx bx-package text-primary"></i></span>
                <input type="text" id="asset_name" name="name" class="form-control form-control-hitech border-start-0 ps-0" placeholder="e.g. Dell Latitude 5420">
              </div>
            </div>
            <div class="col-md-6 mb-2">
              <label class="form-label form-label-hitech">Asset Code <span class="badge bg-label-info ms-2">Auto-Generated</span></label>
              <div class="input-group input-group-merge shadow-none">
                <span class="input-group-text bg-transparent border-end-0"><i class="bx bx-barcode text-primary"></i></span>
                <input type="text" id="asset_code" name="asset_code" class="form-control form-control-hitech border-start-0 ps-0 bg-light" placeholder="Automatically assigned on save..." readonly style="cursor: not-allowed;">
              </div>
            </div>
            
            <div class="col-md-6 mb-2">
              <label class="form-label form-label-hitech">Category <span class="text-danger">*</span></label>
              <select id="asset_category_id" name="category_id" class="form-select form-select-hitech select2-modal" required>
                <option value="">Select Category</option>
                @foreach($categories as $category)
                <option value="{{ $category->id }}">{{ $category->name }}</option>
                @endforeach
              </select>
            </div>
            <div class="col-md-6 mb-2">
              <label class="form-label form-label-hitech">Assign To</label>
              <select id="asset_assigned_to" name="assigned_to" class="form-select form-select-hitech select2-modal">
                <option value="">None</option>
                @if(isset($users))
                  @foreach($users as $user)
                  <option value="{{ $user->id }}">{{ $user->first_name }} {{ $user->last_name }}</option>
                  @endforeach
                @endif
              </select>
            </div>

            <div class="col-md-6 mb-2">
              <label class="form-label form-label-hitech">Status <span class="text-danger">*</span></label>
              <select id="asset_status" name="status" class="form-select form-select-hitech" required>
                <option value="available">Available</option>
                <option value="assigned">Assigned</option>
                <option value="maintenance">Maintenance</option>
                <option value="retired">Retired</option>
              </select>
            </div>
            <div class="col-md-6 mb-2">
              <label class="form-label form-label-hitech">Warranty Expiry <span class="text-muted small">(optional)</span></label>
              <input type="date" id="asset_warranty_expiry" name="warranty_expiry" class="form-control form-control-hitech">
            </div>

            <div class="col-md-6 mb-2">
              <label class="form-label form-label-hitech">Brand</label>
              <input type="text" id="asset_brand" name="brand" class="form-control form-control-hitech" placeholder="e.g. Dell, Apple">
            </div>
            <div class="col-md-6 mb-2">
              <label class="form-label form-label-hitech">Model</label>
              <input type="text" id="asset_model" name="model" class="form-control form-control-hitech" placeholder="e.g. Latitude 5420">
            </div>

            <div class="col-md-6 mb-2">
              <label class="form-label form-label-hitech">Serial Number</label>
              <input type="text" id="asset_serial_number" name="serial_number" class="form-control form-control-hitech" placeholder="Enter serial number">
            </div>
            <div class="col-md-6 mb-2">
              <label class="form-label form-label-hitech">Location</label>
              <input type="text" id="asset_location" name="location" class="form-control form-control-hitech" placeholder="e.g. Dubai Office">
            </div>



            <div class="col-12 d-none" id="dynamicFieldsContainer">
              <div class="border-top pt-4 mt-2">
                <h6 class="text-primary mb-3"><i class="bx bx-list-check me-2"></i>Category Specific Details</h6>
                <div id="dynamicFieldsArea" class="row g-4">
                  <!-- Dynamic fields will be injected here -->
                </div>
              </div>
            </div>

            <div class="col-12">
              <label class="form-label-hitech">Description</label>
              <textarea id="asset_description" name="description" class="form-control form-control-hitech" rows="3" placeholder="Additional details about the asset..."></textarea>
            </div>
          </div>
        </div>
        <div class="modal-footer border-0 px-4 pb-4">
          <button type="button" class="btn btn-label-secondary" data-bs-dismiss="modal">Cancel</button>
          <button type="submit" class="btn btn-hitech px-4 data-submit">Save Asset</button>
        </div>
      </form>
    </div>
  </div>
</div>
