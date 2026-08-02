{{--
    Setu REVIEW modal — resolves the customers that need a human decision (a
    SIMILAR existing customer exists, so it can't be auto-matched: e.g. Unit I
    vs Unit II). Per customer: update an existing row (adopts Setu's name) or
    create a new one. Populated + driven by window.SetuSync
    (resources/assets/js/app/setu-review-common.js). Shared by the Sales Report
    page and the Setu Sales Sync centre — include once per page.
--}}
<div class="modal fade" id="setuReviewModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-xl modal-dialog-scrollable modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header">
        <div>
          <h5 class="modal-title mb-0">Resolve customers — <span id="setuReviewName"></span></h5>
          <small class="text-muted" id="setuReviewPeriod"></small>
        </div>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">
        <p class="small text-muted">
          Each of these Setu customers is similar to — but not identical to — an existing customer, so it needs your decision. Near-identical names can be genuinely different sub-entities (e.g. Unit I vs Unit II); nothing here is merged automatically. Once you Apply, each becomes an exact match and won't return.
        </p>
        <div class="table-responsive">
          <table class="table table-sm align-middle" id="setuReviewTable">
            <thead>
              <tr>
                <th style="min-width:250px;">Setu Customer &amp; month-wise sales</th>
                <th class="text-end">Total</th>
                <th style="width:150px;">Action</th>
                <th style="min-width:280px;">Existing customer to update</th>
              </tr>
            </thead>
            <tbody></tbody>
          </table>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Close</button>
        <button type="button" class="btn btn-hitech-primary" id="setuApplyResolutions">
          <i class="bx bx-check me-1"></i> Apply Resolutions
        </button>
      </div>
    </div>
  </div>
</div>
