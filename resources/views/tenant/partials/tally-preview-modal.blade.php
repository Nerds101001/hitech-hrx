{{--
    Tally sync PREVIEW modal — mirrors setu-preview-modal.blade.php exactly,
    with tally-prefixed ids so it can sit on the same page as the Setu one
    without colliding. Populated by window.TallySync
    (resources/assets/js/app/tally-review-common.js).
--}}
<style>
  #tallyPreviewModal .tally-cell { border-radius: 4px; font-weight: 600; }
  #tallyPreviewModal .tally-fill   { background: #d1f2e1; color: #0f5132; }
  #tallyPreviewModal .tally-over   { background: #ffe9b3; color: #7a5b00; }
  #tallyPreviewModal .tally-new    { background: #cfe2ff; color: #084298; }
  #tallyPreviewModal .tally-review { background: #f0e6d2; color: #7a5b00; font-style: italic; }
  #tallyPreviewModal #tallyAllTable th { white-space: nowrap; background: #f8fafc; }
  #tallyPreviewModal #tallyAllTable td { white-space: nowrap; }
</style>
<div class="modal fade" id="tallyPreviewModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-xl modal-dialog-scrollable modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header">
        <div>
          <h5 class="modal-title mb-0">Sync preview — <span id="tallyPreviewName"></span></h5>
          <small class="text-muted" id="tallyPreviewPeriod"></small>
        </div>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">
        <p class="small text-muted mb-3">Tally is the source of truth. Below is every change that will be applied — customer names and each month's sales, pulled directly from the already-synced local Tally invoices. Reps, brand and city are never touched. Nothing is written until you click <b>Apply Changes</b>.</p>
        <div id="tallyPreviewSummary" class="d-flex flex-wrap gap-2 mb-3"></div>
        <div id="tallyPreviewSections"></div>
        <div id="tallyPreviewReviewNote" class="alert alert-warning py-2 px-3 small mb-0 mt-3" style="display:none;"></div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
        <button type="button" class="btn btn-hitech-primary" id="tallyApplyChanges">
          <i class="bx bx-check me-1"></i> <span class="label">Apply Changes</span>
        </button>
      </div>
    </div>
  </div>
</div>
