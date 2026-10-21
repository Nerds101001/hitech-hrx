{{-- Work Order detail: every item line, matched Delivery Notes, matched invoices, and an editable tracking
     note. Opened by clicking a [data-wo-order] button on the Work Orders page. Include once. --}}
@once
<div class="modal fade wo-modal" id="woModal" tabindex="-1" aria-labelledby="woModalTitle" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable modal-lg" id="woModalDialog">
        <div class="modal-content">
            <div class="modal-header">
                <div class="wo-modal-icon"><i class="bx bx-package"></i></div>
                <div style="min-width:0;flex:1;">
                    <h6 class="modal-title" id="woModalTitle">Work Order</h6>
                    <div class="disp-sub" id="woModalSub"></div>
                </div>
                <button type="button" class="wo-expand-btn" id="woModalExpand" title="Expand"><i class="bx bx-expand-alt"></i></button>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body" id="woModalBody"></div>
        </div>
    </div>
</div>

<style>
    /* Matches the rest of Sales Sources (see layout.blade.php's --ss-hero / --ss-brand): the same navy-to-
       teal gradient as the page's own hero banner, the same #127464 brand teal and #eaf6f3 soft tint used
       on ss-stat/ss-flag everywhere else -- this modal was still on plain default Bootstrap styling while
       every other surface on the page had already been given that treatment, which is what made it look
       disconnected/orphaned from the rest of the site. */
    .wo-modal .modal-content { border: none !important; border-radius: 20px !important; box-shadow: 0 24px 60px rgba(10,39,64,.28) !important; overflow: hidden; }
    .wo-modal .modal-header {
        align-items: center; gap: .85rem; min-height: 0 !important; padding: 1.1rem 1.35rem !important; border: none !important;
        background:
            radial-gradient(420px 220px at 85% -20%, rgba(99,102,241,.28), transparent 65%),
            linear-gradient(115deg, #0a2740 0%, #0d4266 48%, #0f6b78 100%) !important;
    }
    .wo-modal-icon { width: 42px; height: 42px; flex-shrink: 0; display: flex; align-items: center; justify-content: center; border-radius: 13px; background: rgba(255,255,255,.14); border: 1px solid rgba(255,255,255,.22); color: #fff; font-size: 1.3rem; }
    .wo-modal .modal-header .modal-title { color: #fff !important; font-size: 1.05rem !important; font-weight: 700 !important; }
    .wo-modal .modal-header .disp-sub { color: rgba(255,255,255,.76) !important; font-size: .78rem; }
    .wo-modal .btn-close { filter: brightness(0) invert(1); opacity: .75; }
    .wo-modal .btn-close:hover { opacity: 1; }
    /* Expand toggle: widens the dialog from modal-lg (~800px) to a near-fullscreen width so the item/
       recent-orders/recent-invoices tables have room to breathe instead of needing a horizontal scrollbar
       -- explicit request. Plain CSS width swap, not Bootstrap's own modal-fullscreen (that also drops the
       rounded corners/centering this modal is styled around). */
    .wo-expand-btn { width: 30px; height: 30px; flex-shrink: 0; display: flex; align-items: center; justify-content: center; border: none; border-radius: 8px; background: rgba(255,255,255,.14); color: #fff; font-size: 1rem; opacity: .85; }
    .wo-expand-btn:hover { background: rgba(255,255,255,.24); opacity: 1; }
    #woModalDialog.wo-expanded { max-width: min(1400px, 94vw); }
    .wo-modal .modal-body { padding: 1.2rem 1.35rem 1.4rem !important; background: #f7f9fb; }
    .wo-section-title { display: flex; align-items: center; gap: .4rem; font-size: .72rem; font-weight: 700; letter-spacing: .05em; text-transform: uppercase; color: #0e5f52; margin: 1.3rem 0 .6rem; }
    .wo-section-title:first-child { margin-top: 0; }
    .wo-section-title i { font-size: .95rem; }
    .wo-section-title .wo-section-hint { text-transform: none; letter-spacing: 0; font-weight: 400; color: #94a3b8; }
    /* table-layout:fixed + colgroup percentages (set per table below) so the wide free-text column (Item
       or Party) can't stretch the table past the modal and force a horizontal scrollbar -- it ellipsizes
       instead, full value still on hover via title. Explicit request to reduce/control column width. */
    .wo-lines-table { width: 100%; table-layout: fixed; font-size: .8rem; border-collapse: separate; border-spacing: 0; background: #fff; border-radius: 12px; overflow: hidden; box-shadow: 0 1px 2px rgba(15,23,42,.04), 0 4px 14px rgba(15,23,42,.05); }
    .wo-lines-table th { text-align: left; font-size: .62rem; text-transform: uppercase; letter-spacing: .03em; color: #0e5f52; font-weight: 700; padding: .5rem .55rem; background: #eaf6f3; border-bottom: 1px solid #cfe8e1; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
    /* Qty/Rate/Amount headers already carry class="num" in the row templates below, but a header's
       alignment was never actually overridden to match -- it sat left-aligned while its own column's data
       sat right-aligned (.wo-lines-table td.num), so the header label visually hovered over the PREVIOUS
       column's data instead of its own, looking like the numbers were in the wrong column. */
    .wo-lines-table th.num { text-align: right; }
    .wo-lines-table td { padding: .42rem .55rem; border-bottom: 1px solid #f1f5f9; overflow: hidden; }
    .wo-lines-table tbody tr:last-child td { border-bottom: none; }
    .wo-lines-table tbody tr:hover { background: #f8fafc; }
    .wo-lines-table td.num { text-align: right; font-variant-numeric: tabular-nums; }
    .wo-ellip { white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
    .wo-card { border: 1px solid #edf1f5; border-left: 3px solid #cfe8e1; border-radius: 12px; background: #fff; padding: .8rem .9rem; margin-bottom: .6rem; font-size: .82rem; box-shadow: 0 1px 2px rgba(15,23,42,.03); }
    .wo-card:last-child { margin-bottom: 0; }
    .wo-track-form .form-control, .wo-track-form .form-select { border-radius: 8px !important; }
    .wo-track-form .btn-primary { background: #127464 !important; border-color: #127464 !important; border-radius: 8px !important; }
    .wo-item-link { color: #127464; text-decoration: none; font-weight: 600; }
    .wo-item-link:hover { text-decoration: underline; }
    .wo-back-link { display: inline-flex; align-items: center; gap: .3rem; font-size: .78rem; font-weight: 600; color: #127464; text-decoration: none; padding: .3rem .7rem; border-radius: 20px; background: #eaf6f3; }
    .wo-back-link:hover { background: #cfe8e1; color: #0e5f52; }
    .wo-facts { display: grid; grid-template-columns: repeat(auto-fill, minmax(140px, 1fr)); gap: .5rem; font-size: .78rem; }
    .wo-facts > div { background: #fff; border-radius: 10px; padding: .55rem .7rem; box-shadow: 0 1px 2px rgba(15,23,42,.04), 0 2px 8px rgba(15,23,42,.04); }
    .wo-facts .k { font-weight: 700; color: #94a3b8; text-transform: uppercase; font-size: .62rem; letter-spacing: .04em; }
    .wo-facts .v { color: #1e293b; font-weight: 600; }
    .wo-note-box { background: #eaf6f3; border: 1px solid #cfe8e1; border-radius: 12px; padding: .75rem .9rem; margin-bottom: .6rem; font-size: .82rem; color: #0e5f52; }
    .wo-note-box .wo-note-label { color: #127464; font-size: .64rem; text-transform: uppercase; font-weight: 700; letter-spacing: .04em; display: block; margin-bottom: .2rem; }
    .wo-stat { border-radius: 14px; padding: .85rem .9rem; background: #fff; box-shadow: 0 1px 2px rgba(15,23,42,.04), 0 4px 14px rgba(15,23,42,.05); height: 100%; }
    .wo-stat .lbl { font-size: .64rem; text-transform: uppercase; letter-spacing: .04em; color: #94a3b8; font-weight: 700; }
    .wo-stat .val { font-size: 1.1rem; font-weight: 800; color: #0f172a; line-height: 1.3; }
    .wo-stat .sub { font-size: .68rem; color: #94a3b8; }
</style>

<script>
window.__woShowUrlBase = "{{ url('sales-sources/work-orders') }}";
window.__woItemHistoryUrl = "{{ route('sales-sources.work-orders.item-history') }}";
</script>
{{-- The raw-JS block below is kept outside Blade's directive processing entirely. --}}
@verbatim
<script>
(function () {
    if (window.__woModalReady) return;
    window.__woModalReady = true;

    const CSRF = document.querySelector('meta[name="csrf-token"]')?.content || '';
    const MONTHS = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];
    const esc = (s) => String(s == null ? '' : s).replace(/[&<>"']/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
    const qty = (v) => v == null ? '—' : Number(v).toLocaleString('en-IN', { maximumFractionDigits: 3 });
    const inr = (v) => v == null ? '—' : Number(v).toLocaleString('en-IN', { maximumFractionDigits: 2 });
    const date = (s) => { const m = /^(\d{4})-(\d{2})-(\d{2})/.exec(s || ''); return m ? (+m[3]) + ' ' + MONTHS[+m[2] - 1] + ' ' + m[1] : ''; };

    let currentOrderView = null; // { data, orderNo } -- so "← Back to order" can re-render without refetching

    function render(d, orderNo) {
        currentOrderView = { data: d, orderNo: orderNo };
        document.getElementById('woModalTitle').textContent = 'Work order — ' + orderNo;
        const first = d.lines[0] || {};
        document.getElementById('woModalSub').textContent = (first.party_name || '') + ' · ' + date(first.order_date) + ' · ' + (d.company === 'kif' ? 'Keep It Fresh' : 'Hi Tech');

        let h = '<div class="wo-section-title"><i class="bx bx-list-ul"></i>Items <span class="wo-section-hint">(click an item for its full history)</span></div>';
        h += '<table class="wo-lines-table"><colgroup><col style="width:40%"><col style="width:14%"><col style="width:16%"><col style="width:15%"><col style="width:15%"></colgroup>'
           + '<thead><tr><th>Item</th><th>HSN</th><th class="num">Qty</th><th class="num">Rate</th><th class="num">Amount</th></tr></thead><tbody>';
        d.lines.forEach((l) => {
            const nameHtml = l.item_name
                ? '<a href="#" class="wo-item-link wo-ellip" style="display:block;" title="' + esc(l.item_name) + '" data-wo-item="' + esc(l.item_name) + '" data-wo-company="' + esc(d.company) + '">' + esc(l.item_name) + '</a>'
                : '<span class="text-muted">Item detail not synced for this order yet — re-run the sync to fill it in</span>';
            h += '<tr><td>' + nameHtml
               + (l.spec ? '<div class="text-muted wo-ellip" style="font-size:.72rem;" title="' + esc(l.spec) + '">' + esc(l.spec) + '</div>' : '')
               + (l.pack_qty_number ? '<div class="text-muted wo-ellip" style="font-size:.72rem;">Pack: ' + esc(l.pack_qty_number) + '</div>' : '') + '</td>'
               + '<td class="wo-ellip">' + esc(l.hsn || '—') + '</td><td class="num">' + qty(l.quantity) + ' ' + esc(l.unit || '') + '</td>'
               + '<td class="num">' + (l.rate != null ? inr(l.rate) : '—') + '</td><td class="num">' + inr(l.amount) + '</td></tr>';
        });
        h += '</tbody></table>';

        h += '<div class="wo-section-title"><i class="bx bx-receipt"></i>Billing</div>';
        if (!d.invoices.length) {
            h += '<div class="text-muted" style="font-size:.82rem;">Not billed yet.</div>';
        } else {
            d.invoices.forEach((i) => { h += '<div class="wo-card" style="border-left-color:#15803d;">' + esc(i.invoice_no) + ' — ' + date(i.invoice_date) + ' — <strong>₹' + inr(i.amount) + '</strong></div>'; });
        }

        h += '<div class="wo-section-title"><i class="bx bx-truck"></i>Dispatch</div>';
        if (!d.deliveries.length) {
            h += '<div class="text-muted" style="font-size:.82rem;">No Delivery Note referencing this order has been synced yet.</div>';
        } else {
            d.deliveries.forEach((n) => {
                h += '<div class="wo-card" style="border-left-color:#127464;"><strong>' + esc(n.delivery_no) + '</strong> — ' + date(n.delivery_date);
                h += '<div style="margin-top:.3rem;">' + n.items.map((i) => esc(i.item) + ' — ' + qty(i.qty) + ' ' + esc(i.unit || '')).join('<br>') + '</div>';
                const facts = [n.transporter_name, n.vehicle_number, n.transport_mode].filter(Boolean).join(' · ');
                if (facts) {
                    h += '<div class="text-muted" style="font-size:.76rem;margin-top:.3rem;">' + esc(facts) + '</div>';
                } else {
                    h += '<div class="text-muted" style="font-size:.76rem;margin-top:.3rem;">No transporter/vehicle recorded in Tally for this delivery.</div>';
                }
                h += '</div>';
            });
        }

        h += '<div class="wo-section-title"><i class="bx bx-note"></i>Tracking note <span class="wo-section-hint">(manually entered, never overwritten by sync)</span></div>';
        h += '<form class="wo-track-form" id="woTrackForm">'
           + '<div class="row g-2 mb-2">'
           + '<div class="col-6"><input class="form-control form-control-sm" name="carrier" placeholder="Carrier" value="' + esc(d.tracking?.carrier) + '"></div>'
           + '<div class="col-6"><input class="form-control form-control-sm" name="tracking_ref" placeholder="Tracking ref / LR no." value="' + esc(d.tracking?.tracking_ref) + '"></div>'
           + '</div>'
           + '<textarea class="form-control form-control-sm mb-2" name="note" rows="2" placeholder="Notes...">' + esc(d.tracking?.note) + '</textarea>'
           + '<button type="submit" class="btn btn-sm btn-primary">Save tracking</button>'
           + '<span class="ms-2 small text-muted" id="woTrackMsg"></span>'
           + '</form>';

        document.getElementById('woModalBody').innerHTML = h;

        document.getElementById('woTrackForm').addEventListener('submit', async function (e) {
            e.preventDefault();
            const msg = document.getElementById('woTrackMsg');
            msg.textContent = 'Saving...';
            const fd = new FormData(e.target);
            try {
                const r = await fetch(window.__woShowUrlBase + '/' + encodeURIComponent(orderNo) + '/tracking', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': CSRF, 'Accept': 'application/json' },
                    body: JSON.stringify({ company: d.company, carrier: fd.get('carrier'), tracking_ref: fd.get('tracking_ref'), note: fd.get('note') }),
                });
                if (!r.ok) throw new Error(r.status);
                msg.textContent = 'Saved.';
            } catch (err) {
                msg.textContent = 'Could not save — please try again.';
            }
        });
    }

    async function open(orderNo) {
        const el = document.getElementById('woModal'), body = document.getElementById('woModalBody');
        document.getElementById('woModalTitle').textContent = 'Work order — ' + orderNo;
        document.getElementById('woModalSub').textContent = '';
        body.innerHTML = '<div class="text-center text-muted py-5"><div class="spinner-border spinner-border-sm me-2" role="status"></div>Loading…</div>';
        window.bootstrap.Modal.getOrCreateInstance(el).show();
        try {
            const r = await fetch(window.__woShowUrlBase + '/' + encodeURIComponent(orderNo), { headers: { 'Accept': 'application/json' }, credentials: 'same-origin' });
            if (!r.ok) throw new Error(r.status);
            render(await r.json(), orderNo);
        } catch (e) {
            body.innerHTML = '<div class="text-muted">Could not load this order. Please try again.</div>';
        }
    }

    // Everything Tally has for one SKU: every order it was ever ordered on, every invoice it was ever
    // billed on, rate range, customer count. Replaces the order view in-place (not a stacked modal) so
    // "← Back to order" just re-renders the order data already fetched -- no second round trip.
    async function openItemHistory(itemName, company) {
        const body = document.getElementById('woModalBody');
        document.getElementById('woModalTitle').textContent = 'Item — ' + itemName;
        document.getElementById('woModalSub').textContent = '';
        body.innerHTML = '<div class="text-center text-muted py-5"><div class="spinner-border spinner-border-sm me-2" role="status"></div>Loading…</div>';
        try {
            const url = window.__woItemHistoryUrl + '?item=' + encodeURIComponent(itemName) + (company ? '&company=' + encodeURIComponent(company) : '');
            const r = await fetch(url, { headers: { 'Accept': 'application/json' }, credentials: 'same-origin' });
            if (!r.ok) throw new Error(r.status);
            renderItemHistory(await r.json());
        } catch (e) {
            body.innerHTML = '<div class="text-muted">Could not load this item\'s history. Please try again.</div>';
        }
    }

    function renderItemHistory(d) {
        const s = d.summary, sp = d.specs;
        let h = '<a href="#" id="woBackToOrder" class="wo-back-link mb-2"><i class="bx bx-left-arrow-alt"></i>Back to order ' + esc(currentOrderView ? currentOrderView.orderNo : '') + '</a>';

        h += '<div class="wo-section-title"><i class="bx bx-cube"></i>Specifications</div>';
        if (!sp) {
            h += '<div class="text-muted" style="font-size:.82rem;">No item master record synced for this item yet.</div>';
        } else {
            const facts = [
                ['Category', sp.category], ['Group', sp.stock_group], ['Base unit', sp.base_unit], ['Alias', sp.item_alias],
                ['SKU code', sp.sku_code], ['Colour', sp.colour], ['Size', sp.size],
                ['Lamination', sp.lamination == null ? null : (sp.lamination ? 'Yes' : 'No')],
                ['Printing', sp.printing == null ? null : (sp.printing ? 'Yes' : 'No')],
                ['HSN', sp.hsn], ['GST rate', sp.gst_rate != null ? sp.gst_rate + '%' : null],
                ['Current stock', sp.closing_qty != null ? qty(sp.closing_qty) + ' ' + esc(sp.closing_unit || '') : null],
                ['Current cost', sp.closing_rate != null ? inr(sp.closing_rate) : null],
            ].filter((f) => f[1]);
            if (facts.length) {
                h += '<div class="wo-facts mb-2">' + facts.map((f) => '<div><div class="k">' + esc(f[0]) + '</div><div class="v">' + esc(f[1]) + '</div></div>').join('') + '</div>';
            }
            if (sp.narration) h += '<div class="wo-note-box"><span class="wo-note-label">Packaging / spec note</span>' + esc(sp.narration) + '</div>';
            if (sp.description && sp.description !== sp.narration) h += '<div class="wo-note-box"><span class="wo-note-label">Description</span>' + esc(sp.description) + '</div>';
            if (!sp.narration && !sp.description && !facts.length) h += '<div class="text-muted" style="font-size:.82rem;">Item master record exists but has no specification detail recorded in Tally.</div>';
        }

        h += '<div class="wo-section-title"><i class="bx bx-stats"></i>Summary</div>';
        h += '<div class="row g-2 mb-2">'
           + '<div class="col-6 col-md-3"><div class="wo-stat"><div class="lbl">Ordered</div><div class="val">' + qty(s.ordered_qty) + '</div><div class="sub">' + s.order_count + ' orders</div></div></div>'
           + '<div class="col-6 col-md-3"><div class="wo-stat"><div class="lbl">Invoiced</div><div class="val">' + qty(s.invoiced_qty) + '</div><div class="sub">' + s.invoice_count + ' invoices</div></div></div>'
           + '<div class="col-6 col-md-3"><div class="wo-stat"><div class="lbl">Customers</div><div class="val">' + s.customer_count + '</div></div></div>'
           + '<div class="col-6 col-md-3"><div class="wo-stat"><div class="lbl">Rate range</div><div class="val" style="font-size:.95rem;">' + (s.min_rate != null ? inr(s.min_rate) + ' – ' + inr(s.max_rate) : '—') + '</div><div class="sub">avg ' + (s.avg_rate != null ? inr(s.avg_rate) : '—') + '</div></div></div>'
           + '</div>';

        h += '<div class="wo-section-title"><i class="bx bx-shopping-bag"></i>Recent orders</div>';
        if (!d.orders.length) {
            h += '<div class="text-muted" style="font-size:.82rem;">No sale orders for this item.</div>';
        } else {
            h += '<table class="wo-lines-table"><colgroup><col style="width:14%"><col style="width:18%"><col style="width:38%"><col style="width:15%"><col style="width:15%"></colgroup>'
               + '<thead><tr><th>Date</th><th>Order no.</th><th>Party</th><th class="num">Qty</th><th class="num">Rate</th></tr></thead><tbody>';
            d.orders.forEach((o) => {
                h += '<tr><td>' + date(o.order_date) + '</td><td class="wo-ellip">' + esc(o.order_no) + '</td><td class="wo-ellip" title="' + esc(o.party_name) + '">' + esc(o.party_name) + '</td>'
                   + '<td class="num">' + qty(o.quantity) + ' ' + esc(o.unit || '') + '</td><td class="num">' + (o.rate != null ? inr(o.rate) : '—') + '</td></tr>';
            });
            h += '</tbody></table>';
        }

        h += '<div class="wo-section-title"><i class="bx bx-receipt"></i>Recent invoices</div>';
        if (!d.sales.length) {
            h += '<div class="text-muted" style="font-size:.82rem;">No invoices for this item.</div>';
        } else {
            h += '<table class="wo-lines-table"><colgroup><col style="width:14%"><col style="width:18%"><col style="width:38%"><col style="width:15%"><col style="width:15%"></colgroup>'
               + '<thead><tr><th>Date</th><th>Invoice no.</th><th>Party</th><th class="num">Qty</th><th class="num">Rate</th></tr></thead><tbody>';
            d.sales.forEach((o) => {
                h += '<tr><td>' + date(o.invoice_date) + '</td><td class="wo-ellip">' + esc(o.invoice_no) + '</td><td class="wo-ellip" title="' + esc(o.party_name) + '">' + esc(o.party_name) + '</td>'
                   + '<td class="num">' + qty(o.quantity) + ' ' + esc(o.unit || '') + '</td><td class="num">' + (o.rate != null ? inr(o.rate) : '—') + '</td></tr>';
            });
            h += '</tbody></table>';
        }

        document.getElementById('woModalBody').innerHTML = h;
        document.getElementById('woBackToOrder').addEventListener('click', function (e) {
            e.preventDefault();
            if (currentOrderView) render(currentOrderView.data, currentOrderView.orderNo);
        });
    }

    // Expand/collapse the dialog width -- remembered across opens (localStorage), since whoever widens it
    // once almost certainly wants it that way every time, not just for one order.
    function setExpanded(on) {
        const dialog = document.getElementById('woModalDialog'), btn = document.getElementById('woModalExpand');
        dialog.classList.toggle('wo-expanded', on);
        const icon = btn.querySelector('i');
        icon.className = on ? 'bx bx-collapse-alt' : 'bx bx-expand-alt';
        btn.title = on ? 'Collapse' : 'Expand';
        try { localStorage.setItem('wo_modal_expanded', on ? '1' : '0'); } catch (e) {}
    }
    document.getElementById('woModalExpand').addEventListener('click', function () {
        setExpanded(!document.getElementById('woModalDialog').classList.contains('wo-expanded'));
    });
    try { setExpanded(localStorage.getItem('wo_modal_expanded') === '1'); } catch (e) {}

    document.addEventListener('click', function (e) {
        const orderEl = e.target.closest('[data-wo-order]');
        if (orderEl) { e.preventDefault(); open(orderEl.dataset.woOrder); return; }
        const itemEl = e.target.closest('[data-wo-item]');
        if (itemEl) { e.preventDefault(); openItemHistory(itemEl.dataset.woItem, itemEl.dataset.woCompany); return; }
    });
})();
</script>
@endverbatim
@endonce
