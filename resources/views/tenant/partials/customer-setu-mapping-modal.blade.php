{{--
    Customer <-> Setu (Tally) verification modal. Opened by clicking a badge
    in the Sales Report grid's Party Name column. Always shows the nearest
    Setu name-matches (up to 5, whatever the customer's current status) so a
    reviewer can pick one — or reconsider an already-mapped customer, not
    just accept it once and never revisit. A search box below covers the
    case where none of the nearest matches are the right one.
    Driven by resources/assets/js/app/customer-setu-mapping.js
    (window.CustomerSetuMapping).
--}}
<div class="modal fade" id="customerSetuModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title mb-0">Verify against Setu — <span id="csmCustomerName" class="fw-bold"></span></h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">
        <div class="d-flex align-items-start justify-content-between gap-2 mb-3">
          <div id="csmStatusLine" class="small text-muted"></div>
          {{-- Only meaningful once matched -- shown/hidden by JS per customer. --}}
          <button type="button" id="csmSyncMonthBtn" class="btn btn-sm btn-outline-success d-none flex-shrink-0" style="white-space:nowrap;">
            <i class="bx bx-sync"></i> Sync current month
          </button>
        </div>

        <div id="csmSuggestedBlock" class="d-none mb-4">
          <h6 class="fw-bold mb-2"><i class="bx bx-search-alt text-warning me-1"></i> <span id="csmSuggestedTitle">Nearest matches</span></h6>
          <div id="csmSuggestedCard"></div>
        </div>

        {{-- Verified customers are locked -- no re-matching, so there's
             nothing here for a search box to do. --}}
        <div id="csmLockedNotice" class="d-none alert alert-success py-2 small mb-0">
          <i class="bx bx-lock-alt"></i> This customer is verified and locked — it can no longer be re-matched to a different Setu customer.
        </div>

        <div id="csmSearchSection">
          <hr>
          <h6 class="fw-bold mb-2">Search all Setu customers</h6>
          <input type="text" class="form-control mb-3" id="csmSearchInput"
                 placeholder="Start typing a company name (min 2 characters)...">
          <div id="csmSearchResults"></div>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Close</button>
      </div>
    </div>
  </div>
</div>
