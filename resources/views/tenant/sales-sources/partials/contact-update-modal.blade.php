{{-- Credit & Contacts' one write path into Tally: edit a customer's contact person/mobile, preview exactly
     what will change, then confirm before anything is actually sent to Tally. Opened by clicking the pencil
     icon (.tc-edit-btn, data-normalized-name/data-party-name/data-contact-person/data-contact-mobile) on the
     Credit & Contacts table. Include once, admin-only (gated by the caller). --}}
@once
<div class="modal fade tc-modal" id="tcModal" tabindex="-1" aria-labelledby="tcModalTitle" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <div style="min-width:0;">
                    <h6 class="modal-title" id="tcModalTitle">Update contact</h6>
                    <div class="tc-sub" id="tcModalSub"></div>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body" id="tcModalBody"></div>
        </div>
    </div>
</div>

<style>
    .tc-modal .modal-content { border: 1px solid #e8edf3 !important; border-radius: 18px !important; box-shadow: 0 18px 44px rgba(15,23,42,.16) !important; overflow: hidden; }
    .tc-modal .modal-header { align-items: flex-start; min-height: 0 !important; padding: 1rem 1.35rem !important; background: #fff !important; border-bottom: 1px solid #e8edf3 !important; }
    .tc-modal .modal-header .modal-title { color: #0f172a !important; font-size: 1.05rem !important; font-weight: 600 !important; letter-spacing: 0 !important; text-transform: none !important; }
    .tc-modal .modal-header .btn-close { padding: .55rem !important; border-radius: 8px !important; background-color: transparent !important; filter: none !important; opacity: .55 !important; }
    .tc-modal .modal-body { padding: 1.1rem 1.35rem 1.4rem !important; }
    .tc-sub { margin-top: 2px; font-size: .8rem; color: #64748b; }
    .tc-note { display: flex; gap: .55rem; align-items: flex-start; margin: .8rem 0; padding: .6rem .8rem; border-radius: 10px; border: 1px solid #cfe9fb; background: #f0f9ff; color: #075985; font-size: .8rem; }
    .tc-note.warn { border-color: #fedf89; background: #fffaeb; color: #93370d; }
    .tc-note.ok { border-color: #a7f3d0; background: #ecfdf5; color: #065f46; }
    .tc-note i { font-size: 1.05rem; margin-top: 1px; }
    .tc-diff { border: 1px solid #edf1f5; border-radius: 12px; overflow: hidden; margin-bottom: .9rem; }
    .tc-diff-row { display: grid; grid-template-columns: 90px 1fr 24px 1fr; gap: .5rem; align-items: center; padding: .55rem .8rem; font-size: .84rem; }
    .tc-diff-row + .tc-diff-row { border-top: 1px solid #f1f5f9; }
    .tc-diff-row .lbl { font-weight: 600; color: #64748b; font-size: .72rem; text-transform: uppercase; letter-spacing: .03em; }
    .tc-diff-row .old { color: #94a3b8; text-decoration: line-through; }
    .tc-diff-row .new { color: #0f172a; font-weight: 600; }
    .tc-diff-row .arrow { color: #94a3b8; text-align: center; }
</style>

<script>
window.__tcPreviewUrl = "{{ route('sales-sources.parties.contact-preview') }}";
window.__tcUpdateUrl = "{{ route('sales-sources.parties.contact-update') }}";
</script>
@verbatim
<script>
(function () {
    if (window.__tcModalReady) return;
    window.__tcModalReady = true;

    const esc = (s) => String(s == null ? '' : s).replace(/[&<>"']/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
    const csrf = () => document.querySelector('meta[name="csrf-token"]').content;
    let current = null; // { normalizedName, partyName, contactPerson, contactMobile, rowEl }

    function editForm() {
        return '<div class="mb-2"><label class="form-label small fw-bold mb-1">Contact person</label>'
            + '<input type="text" id="tcContactPerson" class="form-control form-control-sm" value="' + esc(current.contactPerson) + '"></div>'
            + '<div class="mb-3"><label class="form-label small fw-bold mb-1">Mobile</label>'
            + '<input type="text" id="tcContactMobile" class="form-control form-control-sm" value="' + esc(current.contactMobile) + '"></div>'
            + '<div class="d-flex gap-2"><button type="button" class="btn btn-primary btn-sm flex-grow-1" id="tcPreviewBtn">Preview change</button>'
            + '<button type="button" class="btn btn-outline-secondary btn-sm" data-bs-dismiss="modal">Cancel</button></div>';
    }

    function diffRow(label, oldVal, newVal) {
        return '<div class="tc-diff-row"><div class="lbl">' + esc(label) + '</div>'
            + '<div class="old">' + (esc(oldVal) || '<span class="text-muted">&mdash; empty &mdash;</span>') + '</div>'
            + '<div class="arrow"><i class="bx bx-right-arrow-alt"></i></div>'
            + '<div class="new">' + (esc(newVal) || '<span class="text-muted">&mdash; empty &mdash;</span>') + '</div></div>';
    }

    function openEdit() {
        document.getElementById('tcModalTitle').textContent = 'Update contact';
        document.getElementById('tcModalSub').textContent = current.partyName;
        document.getElementById('tcModalBody').innerHTML = editForm();
        document.getElementById('tcPreviewBtn').addEventListener('click', doPreview);
    }

    async function doPreview() {
        const contactPerson = document.getElementById('tcContactPerson').value.trim();
        const contactMobile = document.getElementById('tcContactMobile').value.trim();
        const body = document.getElementById('tcModalBody');
        body.innerHTML = '<div class="text-center text-muted py-4"><div class="spinner-border spinner-border-sm me-2" role="status"></div>Checking&hellip;</div>';
        try {
            const r = await fetch(window.__tcPreviewUrl, {
                method: 'POST', credentials: 'same-origin',
                headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': csrf() },
                body: JSON.stringify({ normalized_name: current.normalizedName, contact_person: contactPerson, contact_mobile: contactMobile }),
            });
            const d = await r.json();
            if (!r.ok || !d.ok) throw new Error(d.error || ('HTTP ' + r.status));
            renderPreview(d, contactPerson, contactMobile);
        } catch (e) {
            body.innerHTML = '<div class="tc-note warn"><i class="bx bx-error-circle"></i><div>' + esc(e.message || 'Could not check this change. Please try again.') + '</div></div>'
                + '<button type="button" class="btn btn-outline-secondary btn-sm w-100 mt-2" data-bs-dismiss="modal">Close</button>';
        }
    }

    function renderPreview(d, contactPerson, contactMobile) {
        const body = document.getElementById('tcModalBody');
        if (d.unchanged) {
            body.innerHTML = '<div class="tc-note"><i class="bx bx-info-circle"></i><div>Nothing to change &mdash; this matches what Tally already has.</div></div>'
                + '<button type="button" class="btn btn-outline-secondary btn-sm w-100" data-bs-dismiss="modal">Close</button>';
            return;
        }
        let h = '<div class="tc-note"><i class="bx bx-info-circle"></i><div>This will write to Tally\'s ledger for <strong>' + esc(current.partyName) + '</strong> right now. Nothing else on this ledger is touched.</div></div>';
        h += '<div class="tc-diff">'
            + diffRow('Contact', d.current.contact_person, d.proposed.contact_person)
            + diffRow('Mobile', d.current.contact_mobile, d.proposed.contact_mobile)
            + '</div>';
        h += '<div class="d-flex gap-2"><button type="button" class="btn btn-primary btn-sm flex-grow-1" id="tcConfirmBtn">Confirm &amp; update Tally</button>'
            + '<button type="button" class="btn btn-outline-secondary btn-sm" id="tcBackBtn">Back</button></div>';
        body.innerHTML = h;
        document.getElementById('tcConfirmBtn').addEventListener('click', () => doUpdate(contactPerson, contactMobile));
        document.getElementById('tcBackBtn').addEventListener('click', openEdit);
    }

    async function doUpdate(contactPerson, contactMobile) {
        const body = document.getElementById('tcModalBody');
        body.innerHTML = '<div class="text-center text-muted py-4"><div class="spinner-border spinner-border-sm me-2" role="status"></div>Writing to Tally&hellip;</div>';
        try {
            const r = await fetch(window.__tcUpdateUrl, {
                method: 'POST', credentials: 'same-origin',
                headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': csrf() },
                body: JSON.stringify({ normalized_name: current.normalizedName, contact_person: contactPerson, contact_mobile: contactMobile }),
            });
            const d = await r.json();
            if (!r.ok || !d.ok) throw new Error(d.error || ('HTTP ' + r.status));
            body.innerHTML = '<div class="tc-note ok"><i class="bx bx-check-circle"></i><div>Updated in Tally.</div></div>'
                + '<button type="button" class="btn btn-outline-secondary btn-sm w-100" data-bs-dismiss="modal">Close</button>';
            updateRow(d.confirmed.contact_person, d.confirmed.contact_mobile);
        } catch (e) {
            body.innerHTML = '<div class="tc-note warn"><i class="bx bx-error-circle"></i><div>' + esc(e.message || 'Tally did not accept this change.') + '</div></div>'
                + '<button type="button" class="btn btn-outline-secondary btn-sm w-100 mt-2" data-bs-dismiss="modal">Close</button>';
        }
    }

    function updateRow(contactPerson, contactMobile) {
        if (!current.rowEl) return;
        const cell = current.rowEl.querySelector('.tc-contact-cell');
        if (cell) {
            cell.innerHTML = (!contactPerson && !contactMobile) ? '&mdash;'
                : (contactPerson ? esc(contactPerson) : '&mdash;') + (contactMobile ? '<div class="ss-sub">' + esc(contactMobile) + '</div>' : '');
        }
        current.rowEl.querySelector('.tc-edit-btn').dataset.contactPerson = contactPerson || '';
        current.rowEl.querySelector('.tc-edit-btn').dataset.contactMobile = contactMobile || '';
    }

    document.addEventListener('click', function (e) {
        const btn = e.target.closest('.tc-edit-btn');
        if (!btn) return;
        e.preventDefault();
        current = {
            normalizedName: btn.dataset.normalizedName, partyName: btn.dataset.partyName,
            contactPerson: btn.dataset.contactPerson || '', contactMobile: btn.dataset.contactMobile || '',
            rowEl: btn.closest('tr'),
        };
        window.bootstrap.Modal.getOrCreateInstance(document.getElementById('tcModal')).show();
        openEdit();
    });
})();
</script>
@endverbatim
@endonce
