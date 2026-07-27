{{-- Add / Edit Holiday — location-aware --}}
<div class="modal fade" id="modalAddOrUpdateHoliday" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered modal-md">
    <div class="modal-content modal-content-hitech">
      <div class="modal-header modal-header-hitech">
        <div class="d-flex align-items-center">
          <div class="modal-icon-header me-3"><i class="bx bx-calendar-plus"></i></div>
          <h5 class="modal-title modal-title-hitech mb-0" id="holidayModalTitle">Add Holiday</h5>
        </div>
        <button type="button" class="btn-close-hitech" data-bs-dismiss="modal"><i class="bx bx-x"></i></button>
      </div>

      <div class="modal-body modal-body-hitech">
        <form id="holidayForm">
          @csrf
          <input type="hidden" name="id" id="holidayId">

          <div class="row g-4">
            <div class="col-md-8">
              <label class="form-label-hitech" for="holidayName">Holiday Name <span class="text-danger">*</span></label>
              <input type="text" name="name" id="holidayName" class="form-control form-control-hitech"
                     placeholder="e.g. Diwali" required>
            </div>

            <div class="col-md-4">
              <label class="form-label-hitech" for="holidayCode">Code</label>
              <input type="text" name="code" id="holidayCode" class="form-control form-control-hitech"
                     placeholder="Auto">
              <div class="form-text small">Leave blank to auto-generate.</div>
            </div>

            <div class="col-md-6">
              <label class="form-label-hitech" for="holidayDate">Date <span class="text-danger">*</span></label>
              <input type="date" name="date" id="holidayDate" class="form-control form-control-hitech" required>
            </div>

            <div class="col-md-6">
              <label class="form-label-hitech" for="holidaySiteId">
                Location
                <i class="bx bx-info-circle text-muted"
                   title="Pick a location to apply this holiday only to employees mapped to that site. Leave as All Locations to apply company-wide."></i>
              </label>
              <select name="site_id" id="holidaySiteId" class="form-select form-select-hitech select2">
                <option value="">All Locations (company-wide)</option>
                @foreach($sites as $site)
                  <option value="{{ $site->id }}">{{ $site->name }}</option>
                @endforeach
              </select>
            </div>

            <div class="col-12">
              <label class="form-label-hitech" for="holidayNotes">Notes</label>
              <textarea name="notes" id="holidayNotes" class="form-control form-control-hitech" rows="2"
                        placeholder="e.g. 4 PM off for female staff / morning timing 11:30 AM"></textarea>
              <div class="form-text small">
                Use notes for part-day arrangements (early off, late start). A holiday row always marks a
                <strong>full</strong> day off in attendance and payroll.
              </div>
            </div>
          </div>

          <div class="modal-footer border-0 px-0 pb-0 pt-4 d-flex justify-content-end gap-3">
            <button type="button" class="btn btn-hitech-modal-cancel" data-bs-dismiss="modal">Cancel</button>
            <button type="submit" class="btn btn-hitech-modal-submit">
              Save Holiday <i class="bx bx-check-circle ms-1"></i>
            </button>
          </div>
        </form>
      </div>
    </div>
  </div>
</div>
