<!-- Add/Update Asset Category Modal -->
<div class="modal fade" id="offcanvasAddOrUpdateCategory" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content modal-content-hitech">
      <div class="modal-header modal-header-hitech">
        <div class="d-flex align-items-center">
          <div class="modal-icon-header me-3">
            <i class="bx bx-category fs-3"></i>
          </div>
          <h5 class="modal-title modal-title-hitech" id="offcanvasCategoryLabel">Add Category</h5>
        </div>
        <button type="button" class="btn-close-hitech" data-bs-dismiss="modal" aria-label="Close">
          <i class="bx bx-x"></i>
        </button>
      </div>
      <div class="modal-body modal-body-hitech">
    <form class="add-new-category pt-0" id="assetCategoryForm" onsubmit="return false">
      <input type="hidden" name="id" id="id">
      
      <div class="mb-4">
        <label class="form-label form-label-hitech" for="name">Category Name <span class="text-danger">*</span></label>
        <div class="input-group input-group-merge shadow-none">
          <span id="nameIcon" class="input-group-text bg-transparent border-end-0"><i class="bx bx-category text-primary"></i></span>
          <input type="text" id="name" name="name" class="form-control form-control-hitech border-start-0 ps-0" placeholder="e.g. Laptops, Mobile Phones, Furniture" aria-label="Category Name" aria-describedby="nameIcon" />
        </div>
      </div>
      
      <div class="mb-4">
        <label class="form-label form-label-hitech" for="description">Description</label>
        <textarea id="description" name="description" class="form-control form-control-hitech" rows="3" placeholder="Provide a brief description of the asset types in this category..."></textarea>
      </div>

      <div class="mb-4">
        <label class="form-label form-label-hitech" for="parameters">Required Parameters</label>
        <div class="input-group input-group-merge shadow-none">
          <span id="paramIcon" class="input-group-text bg-transparent border-end-0"><i class="bx bx-list-ul text-primary"></i></span>
          <input type="text" id="parameters" name="parameters" class="form-control form-control-hitech border-start-0 ps-0" placeholder="e.g. RAM, SSD, Processor, Serial Number" />
        </div>
        <div class="form-text text-muted ps-1"><i class="bx bx-info-circle me-1"></i>Comma-separated values. These will be custom fields when adding assets.</div>
      </div>

      <div class="mb-5">
        <label class="form-label form-label-hitech" for="status">Status <span class="text-danger">*</span></label>
        <select id="status" name="status" class="form-select form-select-hitech">
          <option value="active">Active</option>
          <option value="inactive">Inactive</option>
        </select>
      </div>

      <div class="modal-footer border-0 px-4 pb-4">
        <button type="button" class="btn btn-label-secondary" data-bs-dismiss="modal">Cancel</button>
        <button type="submit" class="btn btn-hitech px-4 data-submit">Submit</button>
      </div>
    </form>
  </div>
</div>
</div>
</div>
