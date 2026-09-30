{{-- Dispatch drill-down for one Sales Order: every Delivery Note raised against it (date, items, and
     transporter/vehicle/consignee details when Tally has them recorded). Opened by clicking a dispatch
     flag/link with data-order-no on the Orders page. Include once, outside the animated wrapper. --}}
@once
<div class="modal fade disp-modal" id="dispModal" tabindex="-1" aria-labelledby="dispModalTitle" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <div style="min-width:0;">
                    <h6 class="modal-title" id="dispModalTitle">Dispatch</h6>
                    <div class="disp-sub" id="dispModalSub"></div>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body" id="dispModalBody"></div>
        </div>
    </div>
</div>

<style>
    .disp-modal .modal-content { border: 1px solid #e8edf3 !important; border-radius: 18px !important; box-shadow: 0 18px 44px rgba(15,23,42,.16) !important; overflow: hidden; }
    .disp-modal .modal-header { align-items: flex-start; min-height: 0 !important; padding: 1rem 1.35rem !important; background: #fff !important; border-bottom: 1px solid #e8edf3 !important; }
    .disp-modal .modal-header .modal-title { color: #0f172a !important; font-size: 1.05rem !important; font-weight: 600 !important; letter-spacing: 0 !important; text-transform: none !important; }
    .disp-modal .modal-header .btn-close { padding: .55rem !important; border-radius: 8px !important; background-color: transparent !important; filter: none !important; opacity: .55 !important; }
    .disp-modal .modal-body { padding: 1.1rem 1.35rem 1.4rem !important; }
    .disp-sub { margin-top: 2px; font-size: .8rem; color: #64748b; }
    .disp-summary { display: flex; flex-wrap: wrap; gap: .6rem; margin-bottom: 1.1rem; }
    .disp-sum { flex: 1 1 140px; padding: .65rem .9rem; border: 1px solid #edf1f5; border-radius: 12px; }
    .disp-sum .k { font-size: .66rem; font-weight: 600; letter-spacing: .05em; text-transform: uppercase; color: #64748b; }
    .disp-sum .v { font-size: 1.02rem; font-weight: 700; color: #0f172a; font-variant-numeric: tabular-nums; }
    .disp-sum.pending .v { color: #b54708; }
    .disp-note { display: flex; gap: .55rem; align-items: flex-start; margin: .8rem 0; padding: .6rem .8rem; border-radius: 10px; border: 1px solid #cfe9fb; background: #f0f9ff; color: #075985; font-size: .8rem; }
    .disp-note i { font-size: 1.05rem; margin-top: 1px; }
    .disp-card { border: 1px solid #edf1f5; border-radius: 12px; padding: .9rem 1rem; margin-bottom: .75rem; }
    .disp-card:last-child { margin-bottom: 0; }
    .disp-card .head { display: flex; flex-wrap: wrap; justify-content: space-between; gap: .5rem; align-items: baseline; margin-bottom: .5rem; }
    .disp-card .dno { font-weight: 700; color: #0f172a; }
    .disp-card .ddate { font-size: .8rem; color: #64748b; }
    .disp-items { font-size: .84rem; color: #334155; margin-bottom: .5rem; }
    .disp-items div { padding: .15rem 0; }
    .disp-facts { display: grid; grid-template-columns: repeat(auto-fill, minmax(150px, 1fr)); gap: .4rem 1rem; font-size: .78rem; color: #64748b; }
    .disp-facts .k { font-weight: 600; color: #94a3b8; text-transform: uppercase; font-size: .64rem; letter-spacing: .04em; }
    .disp-facts .v { color: #334155; }
    @media (max-width: 575px) {
        .disp-modal .modal-dialog { margin: .6rem; }
        .disp-modal .modal-header, .disp-modal .modal-body { padding-left: 1rem !important; padding-right: 1rem !important; }
    }
</style>

<script>
// The raw-JS block below turns off Blade processing entirely, so the route URL is resolved here, outside
// it, and handed in through a plain global.
window.__dispDispatchUrl = "{{ route('sales-sources.orders.dispatch') }}";
</script>
@verbatim
<script>
(function () {
    if (window.__dispModalReady) return;
    window.__dispModalReady = true;

    const MONTHS = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];
    const esc = (s) => String(s == null ? '' : s).replace(/[&<>"']/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
    const qty = (v) => v == null ? '—' : Number(v).toLocaleString('en-IN', { maximumFractionDigits: 3 });
    const date = (s) => { const m = /^(\d{4})-(\d{2})-(\d{2})/.exec(s || ''); return m ? (+m[3]) + ' ' + MONTHS[+m[2] - 1] + ' ' + m[1] : ''; };

    function render(d, orderNo) {
        document.getElementById('dispModalTitle').textContent = 'Dispatch — order ' + orderNo;
        document.getElementById('dispModalSub').textContent = d.notes.length + ' delivery note' + (d.notes.length === 1 ? '' : 's');

        let h = '<div class="disp-summary">' + d.ordered.map((o) =>
            '<div class="disp-sum"><div class="k">Ordered</div><div class="v">' + qty(o.qty) + ' ' + esc(o.unit || '') + '</div></div>'
        ).join('') + '</div>';

        if (!d.notes.length) {
            h += '<div class="disp-note"><i class="bx bx-info-circle"></i><div>No Delivery Note referencing this order has been synced yet. It may still exist in Tally.</div></div>';
        } else {
            d.notes.forEach((n) => {
                h += '<div class="disp-card"><div class="head"><div class="dno">' + esc(n.delivery_no) + '</div><div class="ddate">' + esc(date(n.delivery_date)) + '</div></div>';
                h += '<div class="disp-items">' + n.items.map((i) => '<div>' + esc(i.item) + ' — <strong>' + qty(i.qty) + ' ' + esc(i.unit || '') + '</strong></div>').join('') + '</div>';
                const facts = [
                    ['Transporter', n.transporter_name], ['Vehicle', n.vehicle_number], ['Mode', n.transport_mode],
                    ['Document no.', n.document_number], ['Document date', date(n.document_date)],
                    ['Consignee', n.consignee_name], ['Delivered to', n.consignee_address], ['Consignee GSTIN', n.consignee_gstin],
                ].filter((f) => f[1]);
                if (facts.length) {
                    h += '<div class="disp-facts">' + facts.map((f) => '<div><div class="k">' + esc(f[0]) + '</div><div class="v">' + esc(f[1]) + '</div></div>').join('') + '</div>';
                } else {
                    h += '<div class="disp-note"><i class="bx bx-info-circle"></i><div>No transporter/vehicle details were recorded on this delivery note in Tally.</div></div>';
                }
                h += '</div>';
            });
        }
        document.getElementById('dispModalBody').innerHTML = h;
    }

    async function open(orderNo) {
        const el = document.getElementById('dispModal'), body = document.getElementById('dispModalBody');
        document.getElementById('dispModalTitle').textContent = 'Dispatch — order ' + orderNo;
        document.getElementById('dispModalSub').textContent = '';
        body.innerHTML = '<div class="text-center text-muted py-5"><div class="spinner-border spinner-border-sm me-2" role="status"></div>Loading…</div>';
        window.bootstrap.Modal.getOrCreateInstance(el).show();
        try {
            const r = await fetch(window.__dispDispatchUrl + '?order_no=' + encodeURIComponent(orderNo), { headers: { 'Accept': 'application/json' }, credentials: 'same-origin' });
            if (!r.ok) throw new Error(r.status);
            render(await r.json(), orderNo);
        } catch (e) {
            body.innerHTML = '<div class="disp-note" style="border-color:#fedf89;background:#fffaeb;color:#93370d;"><i class="bx bx-error-circle"></i><div>Could not load dispatch details. Please try again.</div></div>';
        }
    }

    document.addEventListener('click', function (e) {
        const el = e.target.closest('[data-order-no]');
        if (el) { e.preventDefault(); open(el.dataset.orderNo); }
    });
})();
</script>
@endverbatim
@endonce
