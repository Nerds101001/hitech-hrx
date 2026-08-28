{{--
    CRM mapping PREVIEW modal — shows which Sales Report customers can be
    auto-linked to the CRM.API-synced customer registry (exact name match)
    and which only look similar and need a human decision. Populated + driven
    by window.CrmMappingSync (resources/assets/js/app/crm-mapping-review-common.js).
    Mirrors the Setu preview modal's shape, simplified: there's no month-wise
    grid here, both sides already live in HRX's own database.
--}}
<div class="modal fade" id="crmMappingPreviewModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-lg modal-dialog-scrollable modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title mb-0">Link to CRM customers — <span id="crmMappingPreviewName"></span></h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">
        <p class="small text-muted mb-3">
          Matches each Sales Report customer (freehand name) against the CRM.API-synced accounts this
          salesperson already owns. An exact name match links automatically; anything only similar — a
          different spelling, a sub-unit — is queued for you to confirm. Nothing is written until you
          click <b>Apply Changes</b>.
        </p>
        <div id="crmMappingPreviewSummary" class="d-flex flex-wrap gap-2 mb-3"></div>
        <div id="crmMappingPreviewSections"></div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
        <button type="button" class="btn btn-hitech-primary" id="crmApplyMapping">
          <i class="bx bx-check me-1"></i> <span class="label">Apply Changes</span>
        </button>
      </div>
    </div>
  </div>
</div>
