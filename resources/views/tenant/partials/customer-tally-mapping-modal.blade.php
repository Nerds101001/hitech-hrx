{{--
    Customer <-> Tally verification modal. Opened by clicking a badge in the
    Sales Report grid's Party Name column. Same shape as
    customer-setu-mapping-modal.blade.php, but sourced from the app's own
    local Tally invoices instead of the external Setu API.
    Driven by resources/assets/js/app/customer-tally-mapping.js
    (window.CustomerTallyMapping).
--}}
<div class="modal fade" id="customerTallyModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title mb-0">Verify against Tally — <span id="ctmCustomerName" class="fw-bold"></span></h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">
        <div class="d-flex align-items-start justify-content-between gap-2 mb-3">
          <div id="ctmStatusLine" class="small text-muted"></div>
          <button type="button" id="ctmSyncMonthBtn" class="btn btn-sm btn-outline-success d-none flex-shrink-0" style="white-space:nowrap;">
            <i class="bx bx-sync"></i> Sync current month
          </button>
        </div>

        <div id="ctmSuggestedBlock" class="d-none mb-4">
          <h6 class="fw-bold mb-2"><i class="bx bx-search-alt text-warning me-1"></i> <span id="ctmSuggestedTitle">Nearest matches</span></h6>
          <div id="ctmSuggestedCard"></div>
        </div>

        <div id="ctmLockedNotice" class="d-none alert alert-success py-2 small mb-0">
          <i class="bx bx-lock-alt"></i> This customer is verified and locked — it can no longer be re-matched to a different Tally party.
        </div>

        <div id="ctmSearchSection">
          <hr>
          <h6 class="fw-bold mb-2">Search Tally parties (recent sales)</h6>
          <input type="text" class="form-control mb-3" id="ctmSearchInput"
                 placeholder="Start typing a company name (min 2 characters)...">
          <div id="ctmSearchResults"></div>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Close</button>
      </div>
    </div>
  </div>
</div>
