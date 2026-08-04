{{-- Find & Claim Customer — search the whole customer database (not just
     what's scoped to this user), see who holds every role on a result, and
     claim one for yourself. Body is rendered entirely by JS (see the script
     block at the bottom of sales-pipeline/index.blade.php) — this partial is
     just the modal shell, matching how the Setu preview/review modals split
     markup vs. behaviour. --}}
<div class="modal fade" id="claimSearchModal" tabindex="-1">
    <div class="modal-dialog modal-lg modal-dialog-scrollable">
        <div class="modal-content border-0 shadow-lg">
            <div class="modal-header" style="background:linear-gradient(90deg,#0d9488,#0f766e);">
                <div>
                    <h5 class="modal-title text-white fw-bold mb-0">Find &amp; Claim a Customer</h5>
                    <small class="text-white-50">Search across every customer — even ones not currently in your view.</small>
                </div>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <input type="text" id="claimSearchInput" class="form-control mb-2" autocomplete="off"
                       placeholder="Type at least 3 letters of the customer name…">
                <div id="claimSearchHint" class="small text-muted mb-2"></div>
                <div id="claimSearchResults"></div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>
