{{-- The "item view" for a Tally invoice: header facts, every item line (product, HSN, quantity, rate, taxable value, GST, total), the totals,
     and what is still open. Opened by clicking any row that carries data-invoice / data-url (see company-sales.blade.php); the data comes from
     TallyAnalytics::bill() through whichever endpoint the page gave the row. Include once per page, outside any animated wrapper. --}}
@once
<div class="modal fade inv-modal" id="invModal" tabindex="-1" aria-labelledby="invModalTitle" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable modal-xl">
        <div class="modal-content">
            <div class="modal-header">
                <div style="min-width:0;">
                    <h6 class="modal-title" id="invModalTitle">Invoice</h6>
                    <div class="inv-sub" id="invModalSub"></div>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body" id="invModalBody"></div>
        </div>
    </div>
</div>

{{-- Item price-analysis modal: opened by clicking an item name inside the invoice modal above, ONLY when
     the including page sets window.ITEM_MODAL_URL (see ledger-detail.blade.php) -- pages that don't set it
     keep item names as plain text, unchanged. Mirrors the same price-history / who-buys-it view the Sales
     Sources > Analytics page's own item drill-down shows (openItem() in analytics-script.blade.php), just
     a self-contained copy here since that one is wired tightly to that page's own modal/chart plumbing. --}}
<div class="modal fade inv-modal" id="itemModal" tabindex="-1" aria-labelledby="itemModalTitle" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <div style="min-width:0;">
                    <h6 class="modal-title" id="itemModalTitle">Item</h6>
                    <div class="inv-sub" id="itemModalSub"></div>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body" id="itemModalBody"></div>
        </div>
    </div>
</div>

<style>
    .inv-modal .modal-content { border: 1px solid #e8edf3 !important; border-radius: 18px !important; box-shadow: 0 18px 44px rgba(15,23,42,.16) !important; overflow: hidden; }
    .inv-modal .modal-header { align-items: flex-start; min-height: 0 !important; padding: 1rem 1.35rem !important; background: #fff !important; border-bottom: 1px solid #e8edf3 !important; }
    .inv-modal .modal-header .modal-title { color: #0f172a !important; font-size: 1.05rem !important; font-weight: 600 !important; letter-spacing: 0 !important; text-transform: none !important; }
    .inv-modal .modal-header .btn-close { padding: .55rem !important; border-radius: 8px !important; background-color: transparent !important; filter: none !important; opacity: .55 !important; }
    .inv-modal .modal-header .btn-close:hover { background-color: #f6f8fb !important; opacity: 1 !important; }
    .inv-modal .modal-body { padding: 1.1rem 1.35rem 1.4rem !important; }
    .inv-modal .inv-sub { margin-top: 2px; font-size: .8rem; color: #64748b; }
    .inv-facts { display: grid; grid-template-columns: repeat(auto-fill, minmax(190px, 1fr)); gap: .75rem 1.25rem; margin-bottom: 1.1rem; padding: .9rem 1rem; background: #f6f8fb; border: 1px solid #edf1f5; border-radius: 12px; }
    .inv-fact .k { font-size: .66rem; font-weight: 600; letter-spacing: .05em; text-transform: uppercase; color: #64748b; }
    .inv-fact .v { font-size: .88rem; font-weight: 500; color: #0f172a; overflow-wrap: anywhere; }
    .inv-fact.big .v { font-size: 1.1rem; font-weight: 700; }
    .inv-table { width: 100%; margin: 0; border-collapse: collapse; font-size: .85rem; }
    .inv-table th { padding: .55rem .7rem; background: #f6f8fb; border-bottom: 1px solid #e8edf3; font-size: .68rem; font-weight: 600; letter-spacing: .05em; text-transform: uppercase; color: #64748b; white-space: nowrap; }
    .inv-table td { padding: .6rem .7rem; border-bottom: 1px solid #f1f4f8; vertical-align: top; color: #334155; }
    .inv-table tbody tr:last-child td { border-bottom: 0; }
    .inv-table .num { text-align: right; white-space: nowrap; font-variant-numeric: tabular-nums; }
    .inv-table .prod { font-weight: 600; color: #0f172a; min-width: 200px; }
    .inv-table .fam { margin-top: 1px; font-size: .74rem; font-weight: 400; color: #64748b; }
    .inv-table tfoot td { padding: .65rem .7rem; border-top: 2px solid #e8edf3; font-weight: 700; color: #0f172a; background: #fbfcfd; }
    .inv-wrap { border: 1px solid #edf1f5; border-radius: 12px; overflow: auto; }
    .inv-sums { display: flex; flex-wrap: wrap; gap: .6rem; margin-top: .9rem; }
    .inv-sum { flex: 1 1 150px; padding: .65rem .9rem; border: 1px solid #edf1f5; border-radius: 12px; }
    .inv-sum .k { font-size: .66rem; font-weight: 600; letter-spacing: .05em; text-transform: uppercase; color: #64748b; }
    .inv-sum .v { font-size: 1.02rem; font-weight: 700; color: #0f172a; font-variant-numeric: tabular-nums; }
    .inv-sum.total { background: #eaf6f3; border-color: #cfe8e1; }
    .inv-note { display: flex; gap: .55rem; align-items: flex-start; margin-top: .8rem; padding: .6rem .8rem; border-radius: 10px; border: 1px solid #cfe9fb; background: #f0f9ff; color: #075985; font-size: .8rem; }
    .inv-note.warn { border-color: #fedf89; background: #fffaeb; color: #93370d; }
    .inv-note.ok { border-color: #bfe9d2; background: #ecfdf3; color: #067647; }
    .inv-note i { font-size: 1.05rem; margin-top: 1px; }
    .cp-inv-row { cursor: pointer; }
    .cp-inv-row:hover > td { background: #f4faf8; }
    .cp-inv-row:focus-visible { outline: 2px solid #127464; outline-offset: -2px; }
    .cp-inv-go { width: 1%; color: #94a3b8; }
    .cp-inv-row:hover .cp-inv-go { color: #127464; }
    @media (max-width: 575px) {
        .inv-modal .modal-dialog { margin: .6rem; }
        .inv-modal .modal-header, .inv-modal .modal-body { padding-left: 1rem !important; padding-right: 1rem !important; }
        .inv-facts { grid-template-columns: repeat(2, minmax(0, 1fr)); gap: .65rem .9rem; padding: .8rem; }
        .inv-wrap { border: 0; overflow: visible; }
        .inv-table thead { display: none; }
        .inv-table, .inv-table tbody, .inv-table tr, .inv-table td, .inv-table tfoot { display: block; width: 100%; }
        .inv-table tbody tr { margin-bottom: .7rem; padding: .35rem 0; border: 1px solid #edf1f5; border-radius: 12px; }
        .inv-table td { display: flex; justify-content: space-between; gap: 1rem; padding: .3rem .8rem; border: 0; text-align: right; }
        .inv-table td::before { content: attr(data-label); flex-shrink: 0; font-size: .68rem; font-weight: 600; letter-spacing: .04em; text-transform: uppercase; color: #64748b; text-align: left; }
        .inv-table td.prod { display: block; min-width: 0; padding-top: .5rem; text-align: left; }
        .inv-table td.prod::before { content: none; }
        .inv-table tfoot tr { display: flex; flex-wrap: wrap; border: 0; }
        .inv-table tfoot td { display: none; }
        .inv-sums .inv-sum { flex-basis: 45%; }
    }

    /* ── Item price-analysis modal ──────────────────────────────────────── */
    .inv-item-link { color: #127464; text-decoration: none; cursor: pointer; }
    .inv-item-link:hover { text-decoration: underline; }
    .inv-seg { display: inline-flex; border: 1px solid #e8edf3; border-radius: 8px; overflow: hidden; }
    .inv-seg button { padding: 4px 11px; font-size: .74rem; font-weight: 600; color: #64748b; background: #fff; border: 0; border-right: 1px solid #e8edf3; cursor: pointer; }
    .inv-seg button:last-child { border-right: 0; }
    .inv-seg button.active { background: #127464; color: #fff; }
    .inv-kpis { display: grid; grid-template-columns: repeat(4, minmax(0, 1fr)); gap: .6rem; margin-bottom: 1rem; }
    .inv-kpi { padding: .6rem .7rem; background: #f6f8fb; border: 1px solid #edf1f5; border-radius: 10px; }
    .inv-kpi .k { font-size: .64rem; font-weight: 600; letter-spacing: .04em; text-transform: uppercase; color: #64748b; }
    .inv-kpi .v { font-size: 1rem; font-weight: 700; color: #0f172a; margin-top: 1px; }
    .inv-kpi .sub { font-size: .68rem; color: #94a3b8; margin-top: 1px; }
    .inv-sec-title { font-size: .8rem; font-weight: 700; color: #0f172a; margin: 0 0 .4rem; }
    @media (max-width: 575px) {
        .inv-kpis { grid-template-columns: repeat(2, minmax(0, 1fr)); }
    }
</style>

@verbatim
<script>
(function () {
    if (window.__invModalReady) return;
    window.__invModalReady = true;

    const MONTHS = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];
    const esc = (s) => String(s == null ? '' : s).replace(/[&<>"']/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
    const money = (v) => v == null ? '—' : Number(v).toLocaleString('en-IN', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    const qty = (v) => v == null ? '—' : Number(v).toLocaleString('en-IN', { maximumFractionDigits: 3 });
    const date = (s) => { const m = /^(\d{4})-(\d{2})-(\d{2})/.exec(s || ''); return m ? (+m[3]) + ' ' + MONTHS[+m[2] - 1] + ' ' + m[1] : ''; };

    function render(d) {
        const inv = d.invoice, lines = d.lines || [], hasVal = !!d.has_value_detail;
        document.getElementById('invModalTitle').textContent = 'Invoice ' + inv.invoice_no;
        document.getElementById('invModalSub').textContent = [inv.party, date(inv.date)].filter(Boolean).join(' · ');

        const facts = [
            ['Customer', inv.party ? inv.party + (inv.party_code ? ' (' + inv.party_code + ')' : '') : ''],
            ['Invoice date', date(inv.date)], ['Series', inv.series], ['Salesperson', inv.salesperson], ['PO number', inv.po],
            ['Plant / destination', inv.plant], ['City', inv.city],
            // Transporter / LR: the OLD file-based import's own columns (inv.transporter / .transporter_phone /
            // .lr_no) are always blank on gateway-synced data -- fall back to the gateway's own extracted fields
            // (transporter_name/transporter_mobile, from the e-way-bill block; bilty_no stands in for LR number).
            ['Transporter', [inv.transporter || inv.transporter_name, inv.transporter_phone || inv.transporter_mobile].filter(Boolean).join(' · ')],
            ['LR number', inv.lr_no],
            ['Credit terms', inv.credit_terms], ['Due date', date(inv.due_date)],
            // Despatch Details -- this company's own custom panel on the invoice (see TallyAnalytics::bill()
            // for where each of these actually lives in Tally).
            ['Bilty No', inv.bilty_no],
            ['Despatch from', inv.despatch_from], ['Despatch date', date(inv.despatch_date)],
            ['Delivery date', date(inv.delivery_date)], ['Despatch terms', inv.despatch_terms],
            ['POD', inv.pod === true ? 'Yes' : (inv.pod === false ? 'No' : '')],
        ].filter((f) => f[1]);
        let h = '<div class="inv-facts">' + facts.map((f) => '<div class="inv-fact"><div class="k">' + esc(f[0]) + '</div><div class="v">' + esc(f[1]) + '</div></div>').join('')
            + '<div class="inv-fact big"><div class="k">Invoice amount (incl. GST)</div><div class="v">₹' + money(inv.amount) + '</div></div></div>';
        if (inv.branch_transfer) h += '<div class="inv-note warn"><i class="bx bx-transfer"></i><div>This is a branch transfer (stock moved between Hi Tech’s own branches), not a customer sale.</div></div>';

        if (!lines.length) {
            h += '<div class="inv-note"><i class="bx bx-info-circle"></i><div>No item lines are recorded for this invoice in the Tally exports.</div></div>';
        } else {
            const cols = hasVal ? ['#', 'Product', 'HSN', 'Qty', 'Rate', 'Taxable (₹)', 'GST %', 'GST (₹)', 'Total (₹)'] : ['#', 'Product', 'Qty'];
            h += '<div class="inv-wrap"><table class="inv-table"><thead><tr>' + cols.map((c, i) => '<th' + (i > 1 ? ' class="num"' : '') + '>' + c + '</th>').join('') + '</tr></thead><tbody>';
            lines.forEach((l, i) => {
                const total = l.taxable != null && l.tax != null ? l.taxable + l.tax : (l.taxable != null ? l.taxable : null);
                const prodLabel = window.ITEM_MODAL_URL
                    ? '<a href="#" class="inv-item-link" data-item="' + esc(l.item) + '" data-unit="' + esc(l.unit || '') + '">' + esc(l.item) + '</a>'
                    : esc(l.item);
                h += '<tr><td data-label="#">' + (i + 1) + '</td>'
                    + '<td class="prod" data-label="Product">' + prodLabel + (l.family ? '<div class="fam">' + esc(l.family) + '</div>' : '') + '</td>';
                if (hasVal) {
                    h += '<td class="num" data-label="HSN">' + esc(l.hsn || '—') + '</td>'
                        + '<td class="num" data-label="Qty">' + qty(l.qty) + (l.unit ? ' ' + esc(l.unit) : '') + '</td>'
                        + '<td class="num" data-label="Rate">' + (l.rate == null ? '—' : '₹' + money(l.rate) + (l.rate_unit ? '/' + esc(l.rate_unit) : '')) + '</td>'
                        + '<td class="num" data-label="Taxable">' + money(l.taxable) + '</td>'
                        + '<td class="num" data-label="GST %">' + (l.gst == null ? '—' : Number(l.gst).toLocaleString('en-IN', { maximumFractionDigits: 2 }) + '%') + '</td>'
                        + '<td class="num" data-label="GST">' + money(l.tax) + '</td>'
                        + '<td class="num" data-label="Total"><strong>' + money(total) + '</strong></td>';
                } else {
                    h += '<td class="num" data-label="Qty">' + qty(l.qty) + (l.unit ? ' ' + esc(l.unit) : '') + '</td>';
                }
                h += '</tr>';
            });
            h += '</tbody>';
            if (hasVal) h += '<tfoot><tr><td colspan="5">Total</td><td class="num">' + money(d.totals.taxable) + '</td><td></td><td class="num">' + money(d.totals.tax) + '</td><td class="num">' + money(d.totals.lines_total) + '</td></tr></tfoot>';
            h += '</table></div>';

            if (hasVal) {
                h += '<div class="inv-sums"><div class="inv-sum"><div class="k">Taxable value</div><div class="v">₹' + money(d.totals.taxable) + '</div></div>'
                    + '<div class="inv-sum"><div class="k">GST</div><div class="v">₹' + money(d.totals.tax) + '</div></div>'
                    + '<div class="inv-sum total"><div class="k">Invoice total</div><div class="v">₹' + money(inv.amount) + '</div></div></div>';
                h += d.reconciles === false
                    ? '<div class="inv-note warn"><i class="bx bx-error-circle"></i><div>The item lines add up to ₹' + money(d.totals.lines_total) + ' but the invoice total is ₹' + money(inv.amount) + ' (a difference of ₹' + money(d.totals.diff) + '), so some charges on the invoice are not itemised here. Tally gives one combined GST figure per line, not CGST / SGST / IGST.</div></div>'
                    : '<div class="inv-note ok"><i class="bx bx-check-circle"></i><div>The item lines add up to the invoice total. Tally gives one combined GST figure per line, not CGST / SGST / IGST.</div></div>';
            } else {
                h += '<div class="inv-note"><i class="bx bx-info-circle"></i><div>This invoice is not in the Tally report that carries rate, HSN and GST (that report covers only part of the invoices), so only the products and quantities are known.</div></div>';
            }
        }

        if ((d.open || []).length) {
            h += d.open.map((o) => '<div class="inv-note warn"><i class="bx bx-time-five"></i><div><strong>Still open: ₹' + money(o.amount) + '</strong> &middot; billed ' + esc(date(o.date)) + ' &middot; ' + o.age + ' day' + (o.age === 1 ? '' : 's') + ' old' + (o.credit_days ? ' &middot; ' + esc(o.credit_days) + ' days credit' : '') + '</div></div>').join('');
        } else {
            h += '<div class="inv-note ok"><i class="bx bx-check-circle"></i><div>Not on Tally’s receivables list under this bill number, so it is settled or was billed against another reference.</div></div>';
        }
        document.getElementById('invModalBody').innerHTML = h;
    }

    async function open(url, no) {
        const el = document.getElementById('invModal'), body = document.getElementById('invModalBody');
        document.getElementById('invModalTitle').textContent = 'Invoice ' + no;
        document.getElementById('invModalSub').textContent = '';
        body.innerHTML = '<div class="text-center text-muted py-5"><div class="spinner-border spinner-border-sm me-2" role="status"></div>Loading the items…</div>';
        window.bootstrap.Modal.getOrCreateInstance(el).show();
        try {
            const r = await fetch(url + encodeURIComponent(no), { headers: { 'Accept': 'application/json' }, credentials: 'same-origin' });
            if (!r.ok) throw new Error(r.status);
            render(await r.json());
        } catch (e) {
            body.innerHTML = '<div class="inv-note warn"><i class="bx bx-error-circle"></i><div>Could not load this invoice’s items' + (e.message === '403' ? ' (you do not have access to Tally figures)' : '') + '. Please try again.</div></div>';
        }
    }

    // ── Item price-analysis modal -- only reachable when the including page sets window.ITEM_MODAL_URL
    //    (render() above only turns an item name into a link in that case), so this never runs otherwise.
    let itemScope = 'period';

    function renderItem(d, name, unit) {
        document.getElementById('itemModalTitle').textContent = name;
        document.getElementById('itemModalSub').textContent = [d.family, d.hsn ? 'HSN ' + d.hsn : '', d.gst != null ? 'GST ' + d.gst + '%' : '', d.unit ? 'per ' + d.unit : ''].filter(Boolean).join(' · ');

        const seg = '<div class="d-flex justify-content-end mb-2"><div class="inv-seg" id="itemSeg">'
            + '<button type="button" data-scope="period" class="' + (itemScope === 'period' ? 'active' : '') + '">This period</button>'
            + '<button type="button" data-scope="all" class="' + (itemScope === 'all' ? 'active' : '') + '">All loaded data</button></div></div>';
        let h = seg;

        const kpi = (k, v, sub) => '<div class="inv-kpi"><div class="k">' + esc(k) + '</div><div class="v">' + v + '</div>' + (sub ? '<div class="sub">' + esc(sub) + '</div>' : '') + '</div>';

        if (!d.value_detail) {
            h += '<div class="inv-note"><i class="bx bx-info-circle"></i><div>No value, rate or GST is known for this item in the selected period.</div></div>'
                + '<div class="inv-kpis">' + kpi('Bills', d.kpis.bills) + kpi('Customers', d.kpis.customers) + kpi('Quantity', qty(d.kpis.qty) + (d.unit ? ' ' + esc(d.unit) : '')) + '</div>';
        } else {
            const k = d.kpis;
            h += '<div class="inv-kpis">'
                + kpi('Taxable value', '₹' + money(k.taxable)) + kpi('GST', '₹' + money(k.tax)) + kpi('Quantity', qty(k.qty) + (d.unit ? ' ' + esc(d.unit) : '')) + kpi('Avg rate', k.avg_rate == null ? '—' : '₹' + money(k.avg_rate), 'taxable ÷ qty')
                + kpi('Lowest rate', k.min_rate == null ? '—' : '₹' + money(k.min_rate)) + kpi('Highest rate', k.max_rate == null ? '—' : '₹' + money(k.max_rate)) + kpi('Bills', k.bills) + kpi('Customers', k.customers)
                + '</div>'
                + '<div class="inv-sec-title">Price over time</div><div id="itemPriceChart" style="min-height:220px;margin-bottom:1rem;"></div>';
        }

        // Item master specs -- "SKU Code" is misleadingly named: for this company it's the item's own
        // WEIGHT PER PIECE in Kg (a decimal, e.g. 0.002), not a text code -- confirmed live, 2026-10-30,
        // against the company's own "SKU Report - Sales Order". Only shown when Tally actually has a
        // value for it (many items don't).
        if (d.specs) {
            const sp = d.specs;
            const factRow = (k, v) => v ? '<div class="inv-fact"><div class="k">' + esc(k) + '</div><div class="v">' + esc(v) + '</div></div>' : '';
            const specFacts = factRow('Weight / piece (Kg)', sp.sku_code && Number(sp.sku_code) > 0 ? sp.sku_code : '')
                + factRow('Colour', sp.colour) + factRow('Size', sp.size) + factRow('Description', sp.description)
                + factRow('Lamination', sp.lamination === null ? '' : (sp.lamination ? 'Yes' : 'No'))
                + factRow('Printing', sp.printing === null ? '' : (sp.printing ? 'Yes' : 'No'));
            if (specFacts) {
                h += '<div class="inv-sec-title mt-3">Item specs</div><div class="inv-facts">' + specFacts + '</div>';
            }
        }

        if (d.restricted) {
            h += '<div class="inv-note"><i class="bx bx-lock-alt"></i><div>Showing only this customer’s own purchases of this item — the company-wide buyer list is limited to admin.</div></div>';
        }

        if (!d.restricted && (d.customers || []).length) {
            h += '<div class="inv-sec-title mt-3">Who buys it</div><div class="inv-wrap mb-3"><table class="inv-table"><thead><tr><th>Customer</th><th class="num">Bills</th><th class="num">Qty</th><th class="num">Avg rate (₹)</th><th class="num">Taxable (₹)</th></tr></thead><tbody>'
                + d.customers.map((c) => '<tr><td class="prod" data-label="Customer">' + esc(c.name) + '</td><td class="num" data-label="Bills">' + c.bills + '</td><td class="num" data-label="Qty">' + qty(c.qty) + '</td><td class="num" data-label="Avg rate">' + (c.avg_rate == null ? '—' : money(c.avg_rate)) + '</td><td class="num" data-label="Taxable">' + money(c.taxable) + '</td></tr>').join('')
                + '</tbody></table></div>';
        }

        if ((d.recent || []).length) {
            h += '<div class="inv-sec-title mt-3">Recent sales' + (d.restricted ? ' (this customer only)' : '') + '</div><div class="inv-wrap"><table class="inv-table"><thead><tr><th>Date</th><th>Invoice</th>' + (d.restricted ? '' : '<th>Customer</th>') + '<th class="num">Qty</th>' + (d.value_detail ? '<th class="num">Rate (₹)</th><th class="num">Taxable (₹)</th>' : '') + '</tr></thead><tbody>'
                + d.recent.map((r) => '<tr><td data-label="Date">' + esc(date(r.date)) + '</td><td class="prod" data-label="Invoice">' + esc(r.invoice_no) + '</td>' + (d.restricted ? '' : '<td data-label="Customer">' + esc(r.party) + '</td>') + '<td class="num" data-label="Qty">' + qty(r.qty) + (r.unit ? ' ' + esc(r.unit) : '') + '</td>' + (d.value_detail ? '<td class="num" data-label="Rate">' + (r.rate == null ? '—' : money(r.rate)) + '</td><td class="num" data-label="Taxable">' + money(r.taxable) + '</td>' : '') + '</tr>').join('')
                + '</tbody></table></div>';
        }

        h += '<p class="small text-muted mt-3 mb-0" style="font-size:.74rem;">' + (itemScope === 'all' ? 'All loaded data' : date(d.range[0]) + ' – ' + date(d.range[1])) + '.</p>';

        document.getElementById('itemModalBody').innerHTML = h;
        document.getElementById('itemSeg').addEventListener('click', function (e) {
            const b = e.target.closest('[data-scope]');
            if (b && b.dataset.scope !== itemScope) { itemScope = b.dataset.scope; openItemModal(name, unit); }
        });

        if (d.value_detail && typeof ApexCharts !== 'undefined' && d.history && d.history.labels.length) {
            const el = document.getElementById('itemPriceChart');
            new ApexCharts(el, {
                chart: { type: 'line', height: 220, toolbar: { show: false }, fontFamily: 'inherit' },
                series: [{ name: 'Weighted rate', data: d.history.avg }, { name: 'Lowest', data: d.history.min }, { name: 'Highest', data: d.history.max }],
                xaxis: { categories: d.history.labels, labels: { style: { fontSize: '10px' } } },
                yaxis: { labels: { formatter: (v) => v == null ? '' : '₹' + money(v) } },
                stroke: { width: [3, 1.5, 1.5], dashArray: [0, 4, 4], curve: 'straight' },
                colors: ['#127464', '#94a3b8', '#f59e0b'], markers: { size: [3, 0, 0] },
                tooltip: { y: { formatter: (v) => v == null ? '—' : '₹' + money(v) } },
                legend: { position: 'top', horizontalAlign: 'right', fontSize: '11px' },
            }).render();
        }
    }

    async function openItemModal(name, unit) {
        const el = document.getElementById('itemModal'), body = document.getElementById('itemModalBody');
        document.getElementById('itemModalTitle').textContent = name;
        document.getElementById('itemModalSub').textContent = '';
        body.innerHTML = '<div class="text-center text-muted py-5"><div class="spinner-border spinner-border-sm me-2" role="status"></div>Loading…</div>';
        window.bootstrap.Modal.getOrCreateInstance(el).show();
        try {
            const r = await fetch(window.ITEM_MODAL_URL + encodeURIComponent(name) + '&unit=' + encodeURIComponent(unit || '') + '&scope=' + itemScope, { headers: { 'Accept': 'application/json' }, credentials: 'same-origin' });
            if (!r.ok) throw new Error(r.status);
            renderItem(await r.json(), name, unit);
        } catch (e) {
            body.innerHTML = '<div class="inv-note warn"><i class="bx bx-error-circle"></i><div>Could not load this item’s figures. Please try again.</div></div>';
        }
    }

    document.addEventListener('click', function (e) {
        const toggle = e.target.closest('.cp-inv-toggle');
        if (toggle) {
            const more = document.querySelectorAll('.cp-inv-more'), open = toggle.dataset.open === '1';
            more.forEach((r) => r.classList.toggle('d-none', open));
            toggle.dataset.open = open ? '0' : '1';
            toggle.textContent = open ? toggle.dataset.showLabel : 'Show fewer';
            return;
        }
        const itemLink = e.target.closest('.inv-item-link');
        if (itemLink) { e.preventDefault(); e.stopPropagation(); openItemModal(itemLink.dataset.item, itemLink.dataset.unit); return; }
        const row = e.target.closest('[data-invoice]');
        if (row && row.dataset.url && !e.target.closest('a, button')) open(row.dataset.url, row.dataset.invoice);
    });
    document.addEventListener('keydown', function (e) {
        if ((e.key === 'Enter' || e.key === ' ') && e.target.matches && e.target.matches('[data-invoice]')) { e.preventDefault(); e.target.click(); }
    });
})();
</script>
@endverbatim
@endonce
