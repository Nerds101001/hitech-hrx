{{--
    Setu sync PREVIEW modal — shows exactly what a sync will change (customer
    names + month-wise sales) before anything is written. Populated by
    window.SetuSync (resources/assets/js/app/setu-review-common.js) from the
    dry preview response; "Apply Changes" commits the auto-tier. Shared by the
    Sales Report page and the Setu Sales Sync centre — include once per page.
--}}
<style>
  #setuPreviewModal .setu-cell { border-radius: 4px; font-weight: 600; }
  #setuPreviewModal .setu-fill   { background: #d1f2e1; color: #0f5132; }
  #setuPreviewModal .setu-over   { background: #ffe9b3; color: #7a5b00; }
  #setuPreviewModal .setu-new    { background: #cfe2ff; color: #084298; }
  #setuPreviewModal .setu-review { background: #f0e6d2; color: #7a5b00; font-style: italic; }
  #setuPreviewModal #setuAllTable th { white-space: nowrap; background: #f8fafc; }
  #setuPreviewModal #setuAllTable td { white-space: nowrap; }
</style>
<div class="modal fade" id="setuPreviewModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-xl modal-dialog-scrollable modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header">
        <div>
          <h5 class="modal-title mb-0">Sync preview — <span id="setuPreviewName"></span></h5>
          <small class="text-muted" id="setuPreviewPeriod"></small>
        </div>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">
        <p class="small text-muted mb-3">Setu is the source of truth. Below is every change that will be applied — customer names and each month's sales. Reps, brand and city are never touched. Nothing is written until you click <b>Apply Changes</b>.</p>
        <div id="setuPreviewSummary" class="d-flex flex-wrap gap-2 mb-3"></div>
        <div id="setuPreviewSections"></div>
        <div id="setuPreviewReviewNote" class="alert alert-warning py-2 px-3 small mb-0 mt-3" style="display:none;"></div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
        <button type="button" class="btn btn-hitech-primary" id="setuApplyChanges">
          <i class="bx bx-check me-1"></i> <span class="label">Apply Changes</span>
        </button>
      </div>
    </div>
  </div>
</div>
