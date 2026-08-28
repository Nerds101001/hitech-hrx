{{--
    CRM mapping REVIEW modal — resolves the Sales Report customers that only
    look similar to a CRM account, so they can't be auto-linked (e.g. a
    different spelling or a sub-unit). Per customer: link to the suggested
    CRM account (or a different one) or skip. Populated + driven by
    window.CrmMappingSync (resources/assets/js/app/crm-mapping-review-common.js).
--}}
<div class="modal fade" id="crmReviewModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-xl modal-dialog-scrollable modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title mb-0">Resolve CRM matches — <span id="crmReviewName"></span></h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">
        <p class="small text-muted">
          Each of these Sales Report customers is similar to — but not identical to — an existing CRM
          account, so it needs your decision. Near-identical names can be genuinely different accounts
          (e.g. Unit I vs Unit II); nothing here is linked automatically. Once you Apply, a linked row
          won't be queued again.
        </p>
        <div class="table-responsive">
          <table class="table table-sm align-middle" id="crmReviewTable">
            <thead>
              <tr>
                <th style="min-width:220px;">Sales Report customer</th>
                <th style="width:220px;">Action</th>
                <th style="min-width:280px;">CRM account to link</th>
              </tr>
            </thead>
            <tbody></tbody>
          </table>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Close</button>
        <button type="button" class="btn btn-hitech-primary" id="crmApplyResolutions">
          <i class="bx bx-check me-1"></i> Apply Resolutions
        </button>
      </div>
    </div>
  </div>
</div>
