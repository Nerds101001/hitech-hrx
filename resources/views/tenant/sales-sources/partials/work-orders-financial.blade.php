{{-- Financial tab: Tally's own "Finance Approval DIO" report, surfaced in HRX with filters + charts.
     ITEM-LINE level (item/stock group per row, same change as work-orders-panel.blade.php) plus credit
     period/outstanding days/total outstanding/credit limit, all per the Finance Approval DIO + Partywise
     Sale Order Tally reports. Self-contained: its own fetch-driven JS, not sharing analytics-script.blade.php
     (a different page). --}}
<div id="woFin">
  {{-- Explicit layout request: 6 stat cards in one row, then all 4 charts (including By sales group, which
       used to be its own separate full-width row below) together in a second row of 4. --}}
  <div class="row g-3 mb-3" id="woFinStats"></div>

  {{-- h-100 on each card -- see work-orders-panel.blade.php's identical note: a row's columns are already
       equal-height, but the card inside wasn't told to fill that height. --}}
  <div class="row g-3 mb-3 align-items-stretch">
    <div class="col-12 col-lg-3"><div class="hitech-card-white p-3 h-100"><h6 class="fw-bold mb-2">Order value by day</h6><div id="woFinTrend"></div></div></div>
    <div class="col-12 col-lg-3"><div class="hitech-card-white p-3 h-100"><h6 class="fw-bold mb-2">By unit <span class="text-muted small">(click to filter)</span></h6><div id="woFinByUnit"></div></div></div>
    <div class="col-12 col-lg-3"><div class="hitech-card-white p-3 h-100"><h6 class="fw-bold mb-2">By approval <span class="text-muted small">(click to filter)</span></h6><div id="woFinByApproval"></div></div></div>
    {{-- Tally's own customer ledger GROUP -- see work-orders-panel.blade.php's identical card for why this
         is a real salesperson/territory/brand bucket, not a guess. Toggle switches this SAME card between
         Sales group and Stock group -- see work-orders-panel.blade.php's identical note. --}}
    <div class="col-12 col-lg-3"><div class="hitech-card-white p-3 h-100">
      <div class="d-flex justify-content-between align-items-center mb-2">
        <h6 class="fw-bold mb-0">By <span id="woFinGroupToggleLabel">sales group</span> <span class="text-muted small fw-normal">(click to filter)</span></h6>
        <div class="btn-group btn-group-sm" id="woFinGroupToggle" role="group">
          <button type="button" class="btn btn-outline-secondary active" data-group-mode="group">Sales</button>
          <button type="button" class="btn btn-outline-secondary" data-group-mode="category">Stock</button>
        </div>
      </div>
      <div id="woFinByGroup"></div>
    </div></div>
  </div>

  <div class="hitech-card-white overflow-hidden mb-3 position-relative">
    <div id="woFinLoading" style="display:none;position:absolute;inset:0;background:rgba(255,255,255,.6);z-index:5;"></div>

    {{-- Filter / sort / search toolbar for the table directly below -- kept inside this same card,
         right above the table, not detached up near the stat cards and charts. --}}
    <div class="p-2 border-bottom">
      <div class="wofin-filter-row">
        <input type="text" class="form-control form-control-sm wofin-f-search" id="woFinSearch" placeholder="Party or order no.">
        <select class="form-select form-select-sm wofin-f-company" id="woFinCompanySel">
          <option value="hitech" @selected($company === 'hitech')>Hi Tech</option>
          <option value="kif" @selected($company === 'kif')>Keep It Fresh</option>
          <option value="" @selected($company === null)>Both</option>
        </select>
        <div class="position-relative wofin-f-date">
          <button type="button" class="btn btn-sm btn-outline-secondary w-100 text-truncate" id="woFinDateToggle"></button>
          {{-- position:fixed, not absolute -- see work-orders-panel.blade.php's identical note: the
               filter row's horizontal scroll (overflow-x:auto) implicitly clips overflow-y too, which cut
               this popover off. Fixed positioning, computed in JS from the button's rect, escapes it. --}}
          <div class="card shadow p-2" id="woFinDatePop" style="display:none;position:fixed;z-index:1050;min-width:260px;">
            <div class="d-flex flex-wrap gap-1 mb-2">
              @foreach(['mtd' => 'This month', 'lm' => 'Last month', 'last30' => 'Last 30 days', 'qtd' => 'This quarter', 'fytd' => 'Financial year', 'all' => 'All data'] as $k => $label)
                <button type="button" class="btn btn-sm btn-outline-secondary wofin-preset" data-preset="{{ $k }}">{{ $label }}</button>
              @endforeach
            </div>
            <div class="d-flex gap-1 align-items-center">
              <input type="date" class="form-control form-control-sm" id="woFinFrom">
              <span class="small text-muted">to</span>
              <input type="date" class="form-control form-control-sm" id="woFinTo">
            </div>
          </div>
        </div>
        <select class="form-select form-select-sm wofin-f-sm" id="woFinUnit">
          <option value="">All units</option>
          @foreach($unitPrefixes as $code => $label)
            <option value="{{ $code }}">{{ $label }}</option>
          @endforeach
        </select>
        <select class="form-select form-select-sm wofin-f-sm" id="woFinCategory">
          <option value="">All stock groups</option>
          @foreach($categories as $c)
            <option value="{{ $c }}">{{ $c }}</option>
          @endforeach
        </select>
        <select class="form-select form-select-sm wofin-f-sm" id="woFinGroup">
          <option value="">All sales groups</option>
        </select>
        <select class="form-select form-select-sm wofin-f-sm" id="woFinApproval">
          <option value="">All approval</option>
          <option value="Ok">Ok</option>
          <option value="Make and Hold">Make and Hold</option>
          <option value="Partial Qty Ok">Partial Qty Ok</option>
        </select>
        <button type="button" class="btn btn-sm btn-outline-secondary" id="woFinReset" title="Reset filters"><i class="bx bx-reset"></i></button>
        {{-- Column show/hide -- same checkbox-list content as the Sales Report page's "Columns" button,
             but NOT Bootstrap's dropdown/Popper (same clipping problem as the date popover: Popper still
             leaves the menu as a DOM descendant of the scrolling row). Manually toggled, position:fixed. --}}
        <div class="position-relative">
          <button type="button" class="btn btn-sm btn-outline-secondary" id="woFinColsBtn">
            <i class="bx bx-columns me-1"></i>Columns
          </button>
          <div class="card shadow p-2" id="woFinColsMenu" style="display:none;position:fixed;z-index:1050;min-width:200px;font-size:.8rem;"></div>
        </div>
      </div>
    </div>

    <div class="table-responsive">
      <table class="table table-sm table-hover ss-table mb-0 wofin-table">
        {{-- See work-orders-panel.blade.php's identical note: every column needs an explicit width here so
             table-layout:fixed never has to auto-distribute leftover space -- hiding a column later toggles
             its <col>, which is what actually avoids the header-overlap corruption that happened when td/th
             alone were hidden on columns with no defined width. --}}
        {{-- Exactly 18 <col> tags, matching the 18 <th> below one-for-one -- a stray 19th <col> here
             previously (from an earlier edit removing 2 columns but only shrinking the list by 1) meant
             table-layout:fixed was quietly ignoring it, functionally harmless but incorrect. Also widens
             the 3 whole-order columns, which were too narrow for their renamed headers to read at all. --}}
        <colgroup>
          <col style="width:5%"><col style="width:6%"><col style="width:7%"><col style="width:6%">
          <col style="width:5%"><col style="width:4%"><col style="width:4%"><col style="width:4%">
          <col style="width:5%"><col style="width:5%"><col style="width:6%"><col style="width:6%"><col style="width:6%">
          <col style="width:5%"><col style="width:5%">
          <col style="width:5%"><col style="width:5%">
          <col style="width:5%"><col style="width:4%">
        </colgroup>
        <thead class="table-light">
          <tr>
              <th class="wofin-sortable" data-sort="order_date">Date</th><th class="wofin-sortable" data-sort="order_no">Order no.</th>
              <th class="wofin-sortable wofin-col-item" data-sort="item_name">Item</th>
              <th class="wofin-sortable wofin-col-party" data-sort="party_name">Party</th>
              <th class="wofin-sortable wofin-col-group" data-sort="sales_group">Sales group</th>
              <th class="wofin-sortable" data-sort="unit">Unit</th>
              <th class="wofin-sortable wofin-col-category" data-sort="stock_group">Stock group</th>
              <th class="ss-num wofin-sortable" data-sort="quantity">Qty</th>
              <th class="ss-num wofin-sortable" data-sort="amount">Amount</th>
              {{-- This ITEM's own still-pending quantity (not the whole order's) -- see
                   work-orders-panel.blade.php's identical note. --}}
              <th class="ss-num" title="This line's own still-pending quantity (matched per item, not the whole order)">Item pending</th>
              {{-- Whole-ORDER totals, one column each -- see work-orders-panel.blade.php's identical note.
                   Order qty/Order amt dropped (redundant with Qty/Amount). --}}
              <th class="ss-num wofin-col-orderroll" title="WHOLE ORDER's already-dispatched quantity, summed across every item on it -- not just this line">Order: dispatched</th>
              <th class="ss-num wofin-col-orderroll" title="WHOLE ORDER's still-pending quantity, summed across every item on it -- not just this line">Order: pending</th>
              <th class="ss-num wofin-col-orderroll" title="Value of only the still-pending portion of the whole order">Order: pending ₹</th>
              <th class="ss-num wofin-sortable" data-sort="credit_period">Credit</th><th class="ss-num wofin-sortable" data-sort="outstanding_days">O/S days</th>
              <th class="ss-num wofin-sortable" data-sort="total_outstanding">O/S amt</th><th class="ss-num wofin-sortable" data-sort="credit_limit">Credit limit</th>
              <th class="wofin-sortable" data-sort="financial_approval">Approval</th><th class="wofin-col-remarks">Remarks</th></tr>
        </thead>
        <tbody id="woFinTableBody"><tr><td colspan="19" class="text-center text-muted py-5">Loading…</td></tr></tbody>
      </table>
    </div>
    <div class="p-3 d-flex flex-wrap justify-content-between align-items-center gap-2">
      <div class="small text-muted" id="woFinTableSummary"></div>
      <div id="woFinPager"></div>
    </div>
  </div>

  {{-- Per-customer rollup: on-hold value (what Finance needs to chase) plus each party's own ledger
       outstanding/credit limit from Tally -- one row per party, not summed across their order lines.
       Moved to the bottom of the page (below the main table), out of the way of the stat cards/charts. --}}
  <div class="hitech-card-white p-3 mb-3">
    <h6 class="fw-bold mb-2">By customer <span class="text-muted small fw-normal">(click a row to filter &middot; sorted by value on hold)</span></h6>
    <div class="table-responsive" style="max-height:260px;">
      <table class="table table-sm ss-table mb-0">
        <thead class="table-light"><tr><th>Party</th><th class="ss-num">Orders</th><th class="ss-num">On hold</th><th class="ss-num">Total outstanding</th><th class="ss-num">Credit limit</th></tr></thead>
        <tbody id="woFinByPartyBody"><tr><td colspan="5" class="text-center text-muted py-3">Loading…</td></tr></tbody>
      </table>
    </div>
  </div>

  <p class="small text-muted mb-4">
    Financial Approval and Remarks are real Tally fields (<code>UDF:UDFFINANCIALAPPROVAL</code>, <code>UDF:VCHREMRK</code>) confirmed against your own "Finance Approval DIO" Tally report. Credit period/outstanding/credit limit are synced separately (ledger outstanding + party master), joined in here by customer. "Sales group" is the customer's own ledger group in Tally (salesperson/territory/brand, depending how that customer was set up) -- real synced data, not a guess. Expected despatch date and print status are on your Tally reports but are not in this page yet -- they need a live Tally probe to confirm the exact field names before they can be added (same methodology as everything else here: never guessed, always verified against your Tally first).
  </p>
</div>

<style>
  .wofin-sortable { cursor: pointer; user-select: none; white-space: nowrap; }
  .wofin-sortable:hover { color: #127464; }
  .wofin-sortable .sort-arrow { font-size: .7rem; opacity: .4; margin-left: 3px; }
  .wofin-sortable.active .sort-arrow { opacity: 1; }
  /* One-row filter bar -- see the identical rule and note in work-orders-panel.blade.php. */
  .wofin-filter-row { display: flex; flex-wrap: nowrap; gap: .4rem; align-items: center; overflow-x: auto; }
  .wofin-filter-row > * { flex: 0 0 auto; }
  .wofin-f-search { flex: 1 1 130px; min-width: 100px; }
  .wofin-f-company { width: 95px; }
  .wofin-f-date { width: 120px; }
  .wofin-f-sm { width: 115px; }
  .wofin-filter-row .form-control, .wofin-filter-row .form-select, .wofin-filter-row .btn { font-size: .76rem; }
  /* Fixed layout + ellipsis on the free-text columns so all columns fit without a horizontal scrollbar
     hiding any of them -- same fix as work-orders-panel.blade.php. Full text is on hover via title, and
     the whole row opens the order (which always has the full detail). */
  .wofin-table { table-layout: fixed; width: 100%; font-size: .68rem; }
  .wofin-table th, .wofin-table td { padding: .3rem .35rem; overflow: hidden; }
  /* Marks the 3 whole-ORDER columns as a distinct kind of number from line-level Qty/Amount -- see
     work-orders-panel.blade.php's identical note. */
  .wofin-col-orderroll { background: #f3f8f7 !important; }
  .wofin-row:hover .wofin-col-orderroll { background: #eaf2f1 !important; }
  /* See work-orders-panel.blade.php's identical note: header text needs the same ellipsis clipping as
     data cells, or it visually spills onto the next column's header at narrow widths. */
  .wofin-table th { white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
  .wofin-col-item { width: 10%; }
  .wofin-col-party { width: 9%; }
  .wofin-col-group { width: 7%; }
  .wofin-col-category { width: 6%; }
  .wofin-col-remarks { width: 6%; }
  .wofin-ellip { max-width: 1px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
  .wofin-row { cursor: pointer; }
  .wofin-row:hover { background: #f8fafc; }
  #woFinByPartyBody tr { cursor: pointer; }
  #woFinByPartyBody tr:hover { background: #f8fafc; }
  /* Column show/hide -- same class name as the Sales Report page's "Columns" dropdown. */
  .wofin-table .sr-col-hidden { display: none !important; }
  /* Excel-style column resize handle -- see work-orders-panel.blade.php's identical note. */
  .wofin-table th { position: relative; }
  .wofin-table .col-resize-handle { position: absolute; top: 0; right: 0; bottom: 0; width: 6px; cursor: col-resize; z-index: 50; user-select: none; }
  .wofin-table .col-resize-handle:hover, .wofin-table .col-resize-handle:active { background: rgba(18,116,100,.25); }
</style>

<script>
window.__woFinDataUrl = {!! json_encode(route('sales-sources.work-orders.financial-data')) !!};
window.__woFinCompany = {!! json_encode($company ?: '') !!};
</script>
@verbatim
<script>
(function () {
    const esc = (s) => String(s == null ? '' : s).replace(/[&<>"']/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
    const inr = (v) => '₹' + Number(v || 0).toLocaleString('en-IN', { maximumFractionDigits: 0 });
    const crore = (v) => '₹' + (Number(v || 0) / 1e7).toFixed(2) + ' Cr';
    const qty = (v) => v == null ? '—' : Number(v).toLocaleString('en-IN', { maximumFractionDigits: 3 });
    const MONTHS = ['Jan','Feb','Mar','Apr','May','Jun','Jul','Aug','Sep','Oct','Nov','Dec'];
    const fdate = (s) => { const m = /^(\d{4})-(\d{2})-(\d{2})/.exec(s || ''); return m ? (+m[3]) + ' ' + MONTHS[+m[2]-1] + ' ' + m[1] : '—'; };
    // Local Y-M-D, NOT toISOString() -- see work-orders-panel.blade.php's identical note (toISOString()
    // silently shifts the date back a day in any timezone ahead of UTC, e.g. IST).
    const ymd = (d) => d.getFullYear() + '-' + String(d.getMonth() + 1).padStart(2, '0') + '-' + String(d.getDate()).padStart(2, '0');

    const todayDate = new Date();
    const todayIso = ymd(todayDate);
    const state = { q: '', company: window.__woFinCompany || '', from: ymd(new Date(Date.now() - 29 * 86400000)), to: todayIso, unit: '', category: '', group: '', approval: '', page: 1, sort: 'order_date', dir: 'desc', presetLabel: 'Last 30 days', groupMode: 'group' };
    let trendChart = null, unitChart = null, groupChart = null, approvalChart = null;
    let lastRenderData = null; // re-render the By sales/stock group chart on toggle without a re-fetch
    let requestSeq = 0; // race-condition guard: only the response to the MOST RECENT request is ever rendered
    let groupOptionsFilled = false; // the <select> is populated once from the first response's sales_groups

    const elSearch = document.getElementById('woFinSearch'), elCompany = document.getElementById('woFinCompanySel'),
          elFrom = document.getElementById('woFinFrom'), elTo = document.getElementById('woFinTo'),
          elGroup = document.getElementById('woFinGroup'), elCategory = document.getElementById('woFinCategory'),
          elDateToggle = document.getElementById('woFinDateToggle'), elDatePop = document.getElementById('woFinDatePop'),
          elColsBtn = document.getElementById('woFinColsBtn'), elColsMenu = document.getElementById('woFinColsMenu'),
          elLoading = document.getElementById('woFinLoading');

    // Opens a position:fixed popover/menu anchored under its trigger button -- see work-orders-panel.blade.php's
    // identical helper for why this escapes the filter row's overflow-x:auto (which implicitly clips
    // overflow-y too) instead of using position:absolute.
    function openFixedPopover(btn, pop) {
        const r = btn.getBoundingClientRect();
        pop.style.display = '';
        const popWidth = pop.offsetWidth || 260;
        let left = r.left;
        if (left + popWidth > window.innerWidth - 8) left = Math.max(8, window.innerWidth - popWidth - 8);
        pop.style.top = (r.bottom + 4) + 'px';
        pop.style.left = left + 'px';
    }

    function updateDateToggleLabel() { elDateToggle.innerHTML = esc(state.presetLabel) + ' <i class="bx bx-chevron-down"></i>'; }

    // Column show/hide -- see work-orders-panel.blade.php's identical setup for the full explanation
    // (same pattern as the Sales Report page's "Columns" button: localStorage, sr-col-hidden class).
    let applyColumnVisibility = null;
    (function setupColumnChooser() {
        const table = document.querySelector('.wofin-table');
        const btn = document.getElementById('woFinColsBtn'), menu = document.getElementById('woFinColsMenu');
        if (!table || !btn || !menu) return;
        const headCells = [...table.tHead.rows[0].cells];
        const KEY = 'hrx.workOrders.hiddenCols.financial';
        let hidden = [];
        try { hidden = JSON.parse(localStorage.getItem(KEY) || '[]') || []; } catch (e) { hidden = []; }
        const label = (c) => c.textContent.replace(/\s+/g, ' ').trim();

        const cols = table.querySelectorAll('colgroup col');
        applyColumnVisibility = () => {
            const off = new Set(headCells.map((c, i) => (i > 1 && hidden.includes(label(c))) ? i : -1).filter(i => i > 1));
            // Hide the <col> track, not just the cells -- see work-orders-panel.blade.php's note on why
            // table-layout:fixed needs this (hiding only td/th corrupted the whole table's layout).
            cols.forEach((c, i) => c.classList.toggle('sr-col-hidden', off.has(i)));
            [table.tHead.rows[0], ...table.tBodies[0].rows].forEach((row) => {
                if (row.cells.length < headCells.length) return; // a loading/empty-state row spanning the whole table
                for (let i = 0; i < headCells.length; i++) { if (row.cells[i]) row.cells[i].classList.toggle('sr-col-hidden', off.has(i)); }
            });
            btn.innerHTML = '<i class="bx bx-columns me-1"></i>Columns' + (off.size ? ' (' + off.size + ' hidden)' : '');
        };
        const save = () => { try { localStorage.setItem(KEY, JSON.stringify(hidden)); } catch (e) {} };

        headCells.forEach((c, i) => {
            if (i < 2) return; // Date and Order no. always shown
            const name = label(c);
            if (!name) return;
            const row = document.createElement('div');
            row.className = 'form-check mb-1';
            const id = 'woFinCol-' + i;
            row.innerHTML = '<input class="form-check-input" type="checkbox" id="' + id + '">'
                + '<label class="form-check-label" for="' + id + '"></label>';
            row.querySelector('label').textContent = name;
            const box = row.querySelector('input');
            box.checked = !hidden.includes(name);
            box.addEventListener('change', () => {
                hidden = box.checked ? hidden.filter((h) => h !== name) : [...new Set([...hidden, name])];
                save(); applyColumnVisibility();
            });
            menu.appendChild(row);
        });
        const showAll = document.createElement('button');
        showAll.type = 'button';
        showAll.className = 'btn btn-sm btn-link p-0 mt-1';
        showAll.textContent = 'Show all columns';
        showAll.addEventListener('click', () => {
            hidden = []; save();
            menu.querySelectorAll('input[type=checkbox]').forEach((b) => { b.checked = true; });
            applyColumnVisibility();
        });
        menu.appendChild(showAll);

        applyColumnVisibility();
    })();

    // Excel-style drag-to-resize columns -- see work-orders-panel.blade.php's identical note.
    (function makeColumnsResizable() {
        const table = document.querySelector('.wofin-table');
        if (!table || !table.tHead) return;
        const prefix = 'colw_wofinTable_';
        const cols = table.querySelectorAll('colgroup col');
        [...table.tHead.rows[0].cells].forEach((th, idx) => {
            const col = cols[idx];
            if (!col) return;
            const saved = localStorage.getItem(prefix + idx);
            if (saved) col.style.width = saved + 'px';
            if (th.querySelector('.col-resize-handle')) return;
            const handle = document.createElement('span');
            handle.className = 'col-resize-handle';
            th.appendChild(handle);
            handle.addEventListener('click', (e) => e.stopPropagation());
            handle.addEventListener('mousedown', (e) => {
                e.preventDefault(); e.stopPropagation();
                const startX = e.pageX, startWidth = th.offsetWidth;
                document.body.style.userSelect = 'none';
                document.body.style.cursor = 'col-resize';
                const onMove = (e2) => { col.style.width = Math.max(40, startWidth + (e2.pageX - startX)) + 'px'; };
                const onUp = () => {
                    document.removeEventListener('mousemove', onMove);
                    document.removeEventListener('mouseup', onUp);
                    document.body.style.userSelect = '';
                    document.body.style.cursor = '';
                    try { localStorage.setItem(prefix + idx, th.offsetWidth); } catch (e3) {}
                };
                document.addEventListener('mousemove', onMove);
                document.addEventListener('mouseup', onUp);
            });
        });
    })();

    function setPreset(key, label) {
        const to = new Date(todayDate);
        let from = new Date(to);
        if (key === 'mtd') { from = new Date(to.getFullYear(), to.getMonth(), 1); }
        else if (key === 'lm') { from = new Date(to.getFullYear(), to.getMonth() - 1, 1); state.to = ymd(new Date(to.getFullYear(), to.getMonth(), 0)); }
        else if (key === 'last30') { from.setDate(from.getDate() - 29); state.to = todayIso; }
        else if (key === 'qtd') { from = new Date(to.getFullYear(), Math.floor(to.getMonth() / 3) * 3, 1); state.to = todayIso; }
        else if (key === 'fytd') { const fy = to.getMonth() >= 3 ? to.getFullYear() : to.getFullYear() - 1; from = new Date(fy, 3, 1); state.to = todayIso; }
        else if (key === 'all') { from = new Date(2020, 0, 1); state.to = todayIso; }
        if (key !== 'lm') state.to = todayIso;
        state.from = ymd(from);
        state.presetLabel = label;
        elFrom.value = state.from; elTo.value = state.to;
        updateDateToggleLabel();
        document.querySelectorAll('.wofin-preset').forEach(b => { b.classList.toggle('btn-primary', b.dataset.preset === key); b.classList.toggle('btn-outline-secondary', b.dataset.preset !== key); });
        state.page = 1;
        elDatePop.style.display = 'none';
        load();
    }

    async function load() {
        state.page = state.page || 1;
        const mySeq = ++requestSeq;
        elLoading.style.display = '';
        const params = new URLSearchParams({ from: state.from, to: state.to, unit: state.unit, category: state.category, group: state.group, approval: state.approval, party: state.q, page: state.page, sort: state.sort, dir: state.dir });
        if (state.company) params.set('company', state.company);
        let data;
        try {
            const r = await fetch(window.__woFinDataUrl + '?' + params.toString(), { headers: { 'Accept': 'application/json' } });
            if (!r.ok) return;
            data = await r.json();
        } catch (e) { return; }
        if (mySeq !== requestSeq) return; // a newer request has since started -- this stale response is discarded
        elLoading.style.display = 'none';
        render(data);
    }

    function chart(id, opts) {
        const el = document.getElementById(id);
        if (!el || !window.ApexCharts) return null;
        const c = new ApexCharts(el, opts);
        c.render();
        return c;
    }

    // See work-orders-panel.blade.php's identical function: one chart card toggles between Sales group and
    // Stock group instead of a separate 5th card. Redraws from the already-fetched response, no re-fetch.
    function renderGroupChart(d) {
        if (!d) return;
        const isCategory = state.groupMode === 'category';
        const series = isCategory ? d.by_category : d.by_group;
        document.getElementById('woFinGroupToggleLabel').textContent = isCategory ? 'stock group' : 'sales group';
        if (groupChart) groupChart.destroy();
        groupChart = chart('woFinByGroup', {
            chart: { type: 'bar', height: 280, toolbar: { show: false }, events: { dataPointSelection: (e, c, cfg) => {
                const g = series[cfg.dataPointIndex];
                if (!g) return;
                const val = g.group === 'Not set' ? '' : g.group;
                if (isCategory) { state.category = val; elCategory.value = val; } else { state.group = val; elGroup.value = val; }
                state.page = 1; load();
            } } },
            series: [{ name: 'Value', data: series.map(g => Number(g.value)) }],
            xaxis: { categories: series.map(g => g.group), labels: { formatter: (v) => crore(v) } },
            // See work-orders-panel.blade.php's identical note: the raw unformatted number printed on the
            // bar was unreadable and overlapped the next row -- the tooltip covers it on hover instead.
            dataLabels: { enabled: false },
            plotOptions: { bar: { horizontal: true, borderRadius: 3 } },
            colors: ['#127464'], tooltip: { y: { formatter: (v) => inr(v) } },
        });
    }

    function render(d) {
        const s = d.stats;
        const lt = s.lead_time || {}, ltBill = lt.to_billing, ltDel = lt.to_delivery;
        document.getElementById('woFinStats').innerHTML = [
            ['Pending value', crore(s.total_value), s.orders + ' orders, ' + s.lines + ' lines'],
            ['Approved (Ok)', crore(s.ok_value), ''],
            ['On hold', crore(s.hold_value), s.hold_count + ' orders'],
            ['Units involved', s.unit_count, ''],
            ['Avg. order → billing', ltBill && ltBill.avg_days != null ? ltBill.avg_days + ' days' : '—', ltBill ? ltBill.n + ' orders, this filter' : ''],
            ['Avg. order → delivery', ltDel && ltDel.avg_days != null ? ltDel.avg_days + ' days' : '—', ltDel ? ltDel.n + ' orders, this filter' : ''],
        ].map(([lbl, val, sub]) => '<div class="col-6 col-lg-2"><div class="ss-stat"><div class="lbl">' + esc(lbl) + '</div><div class="val">' + val + '</div>' + (sub ? '<div class="ss-sub">' + esc(sub) + '</div>' : '') + '</div></div>').join('');

        if (trendChart) trendChart.destroy();
        // See work-orders-panel.blade.php's identical note: short DD/MM labels (not all ~30 of them,
        // ApexCharts picks an evenly-spaced subset via tickAmount) and no always-on bar labels (value
        // shows on hover via the tooltip instead).
        const fdateShort = (s) => { const m = /^(\d{4})-(\d{2})-(\d{2})/.exec(s || ''); return m ? m[3] + '/' + m[2] : ''; };
        trendChart = chart('woFinTrend', {
            chart: { type: 'bar', height: 300, toolbar: { show: false } },
            series: [{ name: 'Order value', data: d.trend.map(t => Number(t.value)) }],
            dataLabels: { enabled: false },
            xaxis: { categories: d.trend.map(t => fdateShort(t.d)), tickAmount: Math.min(10, d.trend.length - 1 || 1), labels: { rotate: -45 } },
            yaxis: { labels: { formatter: (v) => crore(v) } },
            colors: ['#127464'], plotOptions: { bar: { borderRadius: 3, columnWidth: '60%' } },
            tooltip: { y: { formatter: (v) => inr(v) } },
        });

        if (unitChart) unitChart.destroy();
        const unitLabels = d.by_unit.map(u => d.unit_labels[u.unit] || u.unit);
        unitChart = chart('woFinByUnit', {
            chart: { type: 'donut', height: 340, events: { dataPointSelection: (e, c, cfg) => { const u = d.by_unit[cfg.dataPointIndex]; if (u) { state.unit = u.unit; document.getElementById('woFinUnit').value = u.unit; load(); } } } },
            series: d.by_unit.map(u => Number(u.value)), labels: unitLabels,
            legend: { position: 'bottom', fontSize: '11px' }, tooltip: { y: { formatter: (v) => inr(v) } },
        });

        if (approvalChart) approvalChart.destroy();
        approvalChart = chart('woFinByApproval', {
            chart: { type: 'donut', height: 340, events: { dataPointSelection: (e, c, cfg) => { const a = d.by_approval[cfg.dataPointIndex]; if (a) { state.approval = a.status === 'Not set' ? '' : a.status; document.getElementById('woFinApproval').value = state.approval; load(); } } } },
            series: d.by_approval.map(a => Number(a.value)), labels: d.by_approval.map(a => a.status),
            colors: ['#15803d', '#b91c1c', '#f59e0b', '#94a3b8'],
            legend: { position: 'bottom', fontSize: '11px' }, tooltip: { y: { formatter: (v) => inr(v) } },
        });

        lastRenderData = d;
        renderGroupChart(d);

        // The <select> options come from the server (every real customer-ledger group, not just the ones
        // in this filtered page) and are filled once -- re-filling on every response would reset whatever
        // the user just picked mid-click.
        if (!groupOptionsFilled && d.sales_groups) {
            groupOptionsFilled = true;
            elGroup.insertAdjacentHTML('beforeend', d.sales_groups.map(g => '<option value="' + esc(g) + '">' + esc(g) + '</option>').join(''));
        }

        document.getElementById('woFinByPartyBody').innerHTML = d.by_party.length ? d.by_party.map((p) => (
            '<tr class="wofin-byparty-row" data-party="' + esc(p.party) + '">'
            + '<td class="wofin-ellip" title="' + esc(p.party) + '">' + esc(p.party) + '</td>'
            + '<td class="ss-num">' + p.orders + '</td>'
            + '<td class="ss-num' + (p.hold_value > 0 ? ' fw-semibold text-danger' : '') + '">' + (p.hold_value > 0 ? inr(p.hold_value) : '—') + '</td>'
            + '<td class="ss-num">' + (p.total_outstanding != null ? inr(p.total_outstanding) : '—') + '</td>'
            + '<td class="ss-num">' + (p.credit_limit != null ? inr(p.credit_limit) : '—') + '</td></tr>'
        )).join('') : '<tr><td colspan="5" class="text-center text-muted py-3">No orders in this filter.</td></tr>';

        document.querySelectorAll('.wofin-sortable').forEach(th => {
            th.classList.toggle('active', th.dataset.sort === state.sort);
            let arrow = th.querySelector('.sort-arrow');
            if (!arrow) { arrow = document.createElement('i'); arrow.className = 'bx sort-arrow'; th.appendChild(arrow); }
            arrow.className = 'bx sort-arrow ' + (th.dataset.sort === state.sort ? (state.dir === 'asc' ? 'bx-up-arrow-alt' : 'bx-down-arrow-alt') : 'bx-sort-alt-2');
        });

        const t = d.table;
        document.getElementById('woFinTableBody').innerHTML = t.rows.length ? t.rows.map((r) => {
            const badge = r.financial_approval === 'Ok' ? 'ok' : (r.financial_approval === 'Make and Hold' ? 'bad' : 'warn');
            // Whole row opens the order detail modal (document-wide [data-wo-order] listener in
            // work-order-modal.blade.php) -- clicking anywhere on the row works, not just a button.
            return '<tr class="wofin-row" data-wo-order="' + esc(r.order_no) + '">'
                + '<td class="text-nowrap">' + fdate(r.order_date) + '</td><td class="fw-semibold text-nowrap">' + esc(r.order_no) + '</td>'
                + '<td class="wofin-ellip" title="' + esc(r.item_name || '') + '">' + esc(r.item_name || '—') + '</td>'
                + '<td class="wofin-ellip" title="' + esc(r.party_name) + '">' + esc(r.party_name) + '</td>'
                + '<td class="wofin-ellip" title="' + esc(r.sales_group || '') + '">' + esc(r.sales_group || '—') + '</td>'
                + '<td>' + esc(d.unit_labels[r.unit] || r.unit) + '</td>'
                + '<td class="wofin-ellip" title="' + esc(r.stock_group || '') + '">' + esc(r.stock_group || '—') + '</td>'
                + '<td class="ss-num">' + qty(r.quantity) + '</td>'
                + '<td class="ss-num fw-semibold">' + inr(r.amount) + '</td>'
                + '<td class="ss-num">' + qty(r.pending_qty) + '</td>'
                + '<td class="ss-num wofin-col-orderroll">' + qty(r.order_dispatched_qty) + '</td>'
                + '<td class="ss-num wofin-col-orderroll">' + qty(r.order_pending_qty) + '</td>'
                + '<td class="ss-num wofin-col-orderroll">' + inr(r.order_pending_amount) + '</td>'
                + '<td class="ss-num">' + (r.credit_period != null ? r.credit_period + 'd' : '—') + '</td><td class="ss-num">' + (r.outstanding_days != null ? r.outstanding_days : '—') + '</td>'
                + '<td class="ss-num">' + (r.total_outstanding != null ? inr(r.total_outstanding) : '—') + '</td><td class="ss-num">' + (r.credit_limit != null ? inr(r.credit_limit) : '—') + '</td>'
                + '<td><span class="ss-flag ' + badge + '">' + esc(r.financial_approval || 'Not set') + '</span></td>'
                + '<td class="wofin-ellip small" title="' + esc(r.remarks || '') + '">' + esc(r.remarks || '') + '</td></tr>';
        }).join('') : '<tr><td colspan="19" class="text-center text-muted py-5">No work order lines match these filters.</td></tr>';
        if (applyColumnVisibility) applyColumnVisibility(); // tbody was just replaced wholesale -- re-apply hidden columns

        const from = (t.page - 1) * t.per + 1, to = Math.min(t.page * t.per, t.total);
        document.getElementById('woFinTableSummary').textContent = t.total ? ('Showing ' + from + '–' + to + ' of ' + t.total.toLocaleString('en-IN') + ' lines') : '';
        const pages = Math.ceil(t.total / t.per);
        let pager = '';
        if (pages > 1) {
            pager = '<div class="btn-group btn-group-sm">'
                + '<button class="btn btn-outline-secondary" ' + (t.page <= 1 ? 'disabled' : '') + ' data-wofin-page="' + (t.page - 1) + '">Prev</button>'
                + '<button class="btn btn-outline-secondary" disabled>' + t.page + ' / ' + pages + '</button>'
                + '<button class="btn btn-outline-secondary" ' + (t.page >= pages ? 'disabled' : '') + ' data-wofin-page="' + (t.page + 1) + '">Next</button>'
                + '</div>';
        }
        document.getElementById('woFinPager').innerHTML = pager;
    }

    document.getElementById('woFin').addEventListener('click', (e) => {
        const groupModeBtn = e.target.closest('[data-group-mode]');
        if (groupModeBtn) {
            state.groupMode = groupModeBtn.dataset.groupMode;
            document.getElementById('woFinGroupToggle').querySelectorAll('[data-group-mode]').forEach((b) => b.classList.toggle('active', b === groupModeBtn));
            renderGroupChart(lastRenderData);
            return;
        }
        const p = e.target.closest('.wofin-preset'); if (p) { setPreset(p.dataset.preset, p.textContent.trim()); return; }
        const pg = e.target.closest('[data-wofin-page]'); if (pg) { state.page = Number(pg.dataset.wofinPage); load(); return; }
        const sortTh = e.target.closest('.wofin-sortable');
        if (sortTh) {
            const key = sortTh.dataset.sort;
            state.dir = (state.sort === key && state.dir === 'desc') ? 'asc' : 'desc';
            state.sort = key; state.page = 1; load(); return;
        }
        const partyRow = e.target.closest('.wofin-byparty-row');
        if (partyRow) { state.q = partyRow.dataset.party; elSearch.value = state.q; state.page = 1; load(); return; }
        const dt = e.target.closest('#woFinDateToggle');
        if (dt) {
            const willOpen = elDatePop.style.display === 'none';
            elColsMenu.style.display = 'none';
            if (willOpen) openFixedPopover(elDateToggle, elDatePop); else elDatePop.style.display = 'none';
            return;
        }
        const colsBtn = e.target.closest('#woFinColsBtn');
        if (colsBtn) {
            const willOpen = elColsMenu.style.display === 'none';
            elDatePop.style.display = 'none';
            if (willOpen) openFixedPopover(elColsBtn, elColsMenu); else elColsMenu.style.display = 'none';
            return;
        }
        if (!e.target.closest('#woFinDatePop')) elDatePop.style.display = 'none';
        if (!e.target.closest('#woFinColsMenu')) elColsMenu.style.display = 'none';
        const reset = e.target.closest('#woFinReset');
        if (reset) {
            state.q = ''; state.unit = ''; state.category = ''; state.group = ''; state.approval = ''; state.page = 1; state.sort = 'order_date'; state.dir = 'desc';
            elSearch.value = ''; document.getElementById('woFinUnit').value = ''; document.getElementById('woFinCategory').value = ''; elGroup.value = ''; document.getElementById('woFinApproval').value = '';
            setPreset('last30', 'Last 30 days');
        }
    });

    let searchDebounce = null;
    elSearch.addEventListener('input', (e) => {
        clearTimeout(searchDebounce);
        searchDebounce = setTimeout(() => { state.q = e.target.value; state.page = 1; load(); }, 350);
    });
    elCompany.addEventListener('change', (e) => { state.company = e.target.value; state.page = 1; load(); });
    elGroup.addEventListener('change', (e) => { state.group = e.target.value; state.page = 1; load(); });
    elFrom.addEventListener('change', (e) => { state.from = e.target.value; state.presetLabel = fdate(state.from) + ' – ' + fdate(state.to); updateDateToggleLabel(); state.page = 1; load(); });
    elTo.addEventListener('change', (e) => { state.to = e.target.value; state.presetLabel = fdate(state.from) + ' – ' + fdate(state.to); updateDateToggleLabel(); state.page = 1; load(); });
    document.getElementById('woFinUnit').addEventListener('change', (e) => { state.unit = e.target.value; state.page = 1; load(); });
    document.getElementById('woFinCategory').addEventListener('change', (e) => { state.category = e.target.value; state.page = 1; load(); });
    document.getElementById('woFinApproval').addEventListener('change', (e) => { state.approval = e.target.value; state.page = 1; load(); });

    // Waits for window.ApexCharts specifically, not a fixed setTimeout -- see earlier note in this file's
    // history: this script runs mid-parse, before the page's vendor-script bundle (apex-charts.min.js,
    // loaded separately by Vite) has necessarily finished loading.
    (function waitForApex(triesLeft) {
        if (window.ApexCharts || triesLeft <= 0) {
            setPreset('last30', 'Last 30 days');
        } else {
            setTimeout(() => waitForApex(triesLeft - 1), 50);
        }
    })(100); // up to 5s
})();
</script>
@endverbatim
